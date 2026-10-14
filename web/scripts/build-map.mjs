// Makes src/mapShapes.ts: the SVG paths of the map of the entities (EntityMap).
// The source is Natural Earth (public domain), version 5.1.2: the provinces of Argentina (admin 1), and the
// countries as Argentina sees them (admin 0, point of view ARG), with the Islas Malvinas, the Georgias del Sur and
// the Sandwich del Sur as part of Argentina.
//
//   node scripts/build-map.mjs [cache folder]
//
// The script runs only when the map changes. The site uses the generated file, not these libraries.

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { tmpdir } from 'node:os'
import { geoAzimuthalEquidistant, geoCentroid, geoPath, geoTransverseMercator } from 'd3-geo'
import { feature, merge } from 'topojson-client'
import { topology } from 'topojson-server'
import { filter, filterWeight, presimplify, quantile, simplify } from 'topojson-simplify'

const SOURCE = 'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/v5.1.2/geojson'
const cache = process.argv[2] ?? join(tmpdir(), 'dxfondito-map')
const OUT = new URL('../src/mapShapes.ts', import.meta.url)

/** The neighbours on the map, by the code of Natural Earth. The names are the names of web/src/entities.ts. */
const NEIGHBOURS = { CHL: 'Chile', BOL: 'Bolivia', PRY: 'Paraguay', BRA: 'Brasil', URY: 'Uruguay' }
const SOUTH_ATLANTIC = 'AR-Antártida e Islas del Atlántico Sur'

// The size of the map in SVG units, and the area of the main map in degrees.
const WIDTH = 320
const HEIGHT = 600
const VIEW = { west: -76.5, east: -51, south: -55.6, north: -20.5 }
// The inset of the Antarctic sector of Argentina (25°W to 74°W, south of 60°S) and the islands of the South Atlantic.
const INSET = { x: 210, y: 494, size: 104 }
const SECTOR = { west: -74, east: -25, north: -60 }

async function load(name) {
  mkdirSync(cache, { recursive: true })
  const file = join(cache, `${name}.geojson`)
  if (!existsSync(file)) {
    console.log(`Downloading ${name}…`)
    const response = await fetch(`${SOURCE}/${name}.geojson`)
    if (!response.ok) throw new Error(`${name}: HTTP ${response.status}`)
    writeFileSync(file, Buffer.from(await response.arrayBuffer()))
  }
  return JSON.parse(readFileSync(file, 'utf8'))
}

const inBox = (box) => (polygon) => {
  const [lon, lat] = geoCentroid({ type: 'Polygon', coordinates: polygon })
  return lon >= box[0] && lon <= box[2] && lat >= box[1] && lat <= box[3]
}

/** The polygons of a geometry that match a test, as a MultiPolygon feature. */
function part(geometry, test, properties) {
  const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates
  return { type: 'Feature', properties, geometry: { type: 'MultiPolygon', coordinates: polygons.filter(test) } }
}

const provinces = (await load('ne_10m_admin_1_states_provinces')).features.filter((f) => f.properties.adm0_a3 === 'ARG')
const countries = (await load('ne_10m_admin_0_countries_arg')).features
const country = (code) => countries.find((f) => f.properties.ADM0_A3 === code)
const argentina = country('ARG')

const features = {
  provinces: provinces.map((f) => ({ type: 'Feature', properties: { key: `AR-${f.properties.name}` }, geometry: f.geometry })),
  neighbours: Object.entries(NEIGHBOURS).map(([code, name]) => ({ ...country(code), properties: { key: name } })),
  malvinas: [part(argentina.geometry, inBox([-62, -53, -57, -50.8]), { key: SOUTH_ATLANTIC })],
  islands: [part(argentina.geometry, inBox([-40, -60, -25, -53]), { key: SOUTH_ATLANTIC })],
  antarctica: [{ ...country('ATA'), properties: {} }],
}

// One topology: the shared borders stay shared after the simplification. Thus no gaps between the provinces.
let topo = topology(Object.fromEntries(Object.entries(features).map(([k, list]) => [k, { type: 'FeatureCollection', features: list }])), 1e6)
topo = presimplify(topo)
topo = simplify(topo, quantile(topo, 0.06))
// Small islands are not visible at this size.
topo = filter(topo, filterWeight(topo, 0.002))

const collection = (name) => feature(topo, topo.objects[name])

// The main map: Transverse Mercator on the meridian 64°W, as the official maps of Argentina.
const main = geoTransverseMercator().rotate([64, 0]).fitExtent(
  [
    [6, 6],
    [WIDTH - 6, HEIGHT - 6],
  ],
  // Points, not a polygon: the winding of a spherical polygon decides its inside.
  {
    type: 'MultiPoint',
    coordinates: [
      [VIEW.west, VIEW.south],
      [VIEW.east, VIEW.south],
      [VIEW.east, VIEW.north],
      [VIEW.west, VIEW.north],
      [(VIEW.west + VIEW.east) / 2, VIEW.south],
      [(VIEW.west + VIEW.east) / 2, VIEW.north],
    ],
  },
)
main.clipExtent([
  [0, 0],
  [WIDTH, HEIGHT],
])
const mainPath = geoPath(main).digits(1)

// The inset: the South Pole at the bottom, the meridian 50°W up.
const inset = geoAzimuthalEquidistant().rotate([50, 90])
const sectorOutline = {
  type: 'Polygon',
  coordinates: [
    [
      ...Array.from({ length: 50 }, (_, i) => [SECTOR.west + ((SECTOR.east - SECTOR.west) * i) / 49, SECTOR.north]),
      [0, -90],
      [SECTOR.west, SECTOR.north],
    ],
  ],
}
inset.fitExtent(
  [
    [INSET.x + 6, INSET.y + 6],
    [INSET.x + INSET.size - 6, INSET.y + INSET.size - 6],
  ],
  {
    type: 'MultiPoint',
    coordinates: [
      [SECTOR.west, SECTOR.north],
      [SECTOR.east, SECTOR.north],
      [-50, -90],
      [-37, -53.8],
      [-26, -56],
    ],
  },
)
inset.clipExtent([
  [INSET.x, INSET.y],
  [INSET.x + INSET.size, INSET.y + INSET.size],
])
const insetPath = geoPath(inset).digits(1)

const regions = []
for (const name of ['neighbours', 'provinces', 'malvinas']) {
  for (const f of collection(name).features) {
    const d = mainPath(f)
    if (d) regions.push({ key: f.properties.key, d })
  }
}
for (const f of collection('islands').features) {
  regions.push({ key: SOUTH_ATLANTIC, d: insetPath(f) })
}
const antarctica = merge(topo, topo.objects.antarctica.geometries)
const caba = main(geoCentroid(provinces.find((f) => f.properties.name === 'Ciudad de Buenos Aires')))
// The labels of the neighbours, at chosen points.
const labels = [
  ['Chile', -72.6, -36.5],
  ['Bolivia', -64.5, -21],
  ['Paraguay', -58.5, -22.5],
  ['Brasil', -53.5, -24.5],
  ['Uruguay', -55.6, -32.4],
].map(([text, lon, lat]) => {
  const [x, y] = main([lon, lat])
  return { text, x: Math.round(x), y: Math.round(y) }
})

const map = {
  width: WIDTH,
  height: HEIGHT,
  regions,
  caba: { x: Math.round(caba[0] * 10) / 10, y: Math.round(caba[1] * 10) / 10 },
  labels,
  inset: {
    ...INSET,
    /** All the Antarctic land in the inset: the part out of the sector stays neutral. */
    land: insetPath(antarctica),
    /** The sector: the clip of the land of the sector, and the region of the entity. */
    sector: insetPath(sectorOutline),
    key: SOUTH_ATLANTIC,
  },
}

writeFileSync(
  OUT,
  `// Generated by scripts/build-map.mjs from Natural Earth 5.1.2 (public domain). Do not edit.\n\n` +
    `export const MAP = ${JSON.stringify(map, null, 2)} as const\n`,
)
console.log(`${OUT.pathname}: ${regions.length} regions, ${Math.round(JSON.stringify(map).length / 1024)} KB`)
