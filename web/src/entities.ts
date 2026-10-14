// The entities of the contacts: the provinces of Argentina, and each other country.

/** A province of Argentina, or a different country. */
export interface Entity {
  /** Such as "AR-Buenos Aires" for a province, or "Uruguay" for a country: the keys of the regions of the map. */
  key: string
  name: string
  kind: 'province' | 'country'
}

/** The provinces of Argentina by the first letter of the suffix, such as D in LU1DAB. */
const PROVINCES: Record<string, string> = {
  A: 'Ciudad de Buenos Aires',
  B: 'Ciudad de Buenos Aires',
  C: 'Ciudad de Buenos Aires',
  D: 'Buenos Aires',
  E: 'Buenos Aires',
  F: 'Santa Fe',
  H: 'Córdoba',
  I: 'Misiones',
  J: 'Entre Ríos',
  K: 'Tucumán',
  L: 'Corrientes',
  M: 'Mendoza',
  N: 'Santiago del Estero',
  O: 'Salta',
  P: 'San Juan',
  Q: 'San Luis',
  R: 'Catamarca',
  S: 'La Rioja',
  T: 'Jujuy',
  U: 'La Pampa',
  V: 'Río Negro',
  W: 'Chubut',
  Y: 'Neuquén',
  Z: 'Antártida e Islas del Atlántico Sur',
}

/** G and X need the second letter of the suffix. */
const SPLIT_PROVINCES: Record<string, { last: string; first: string; second: string }> = {
  G: { last: 'O', first: 'Chaco', second: 'Formosa' },
  X: { last: 'O', first: 'Santa Cruz', second: 'Tierra del Fuego' },
}

/** All the province names. */
export const PROVINCE_NAMES: ReadonlySet<string> = new Set([
  ...Object.values(PROVINCES),
  ...Object.values(SPLIT_PROVINCES).flatMap(({ first, second }) => [first, second]),
])

/** An Argentine call sign: the prefix (AY, AZ, L2 to L9, LO to LW), one digit, and the suffix. */
const ARGENTINE = /^(?:A[YZ]|L[2-9]|L[O-W])\d([A-Z]+)$/

/**
 * The prefixes of the countries (ITU, Appendix 42 of the Radio Regulations), by range of the first two characters.
 * A range of one character, such as "F", has all the prefixes that start with it.
 */
const COUNTRIES: [string, string][] = [
  ['2', 'Reino Unido'],
  ['3A', 'Mónaco'],
  ['3B', 'Mauricio'],
  ['3C', 'Guinea Ecuatorial'],
  ['3D', 'Fiyi'],
  ['3E-3F', 'Panamá'],
  ['3G', 'Chile'],
  ['3H-3U', 'China'],
  ['3V', 'Túnez'],
  ['3W', 'Vietnam'],
  ['3X', 'Guinea'],
  ['3Y', 'Noruega'],
  ['3Z', 'Polonia'],
  ['4A-4C', 'México'],
  ['4D-4I', 'Filipinas'],
  ['4J-4K', 'Azerbaiyán'],
  ['4L', 'Georgia'],
  ['4M', 'Venezuela'],
  ['4O', 'Montenegro'],
  ['4P-4S', 'Sri Lanka'],
  ['4T', 'Perú'],
  ['4U', 'Naciones Unidas'],
  ['4V', 'Haití'],
  ['4W', 'Timor Oriental'],
  ['4X', 'Israel'],
  ['4Z', 'Israel'],
  ['5A', 'Libia'],
  ['5B', 'Chipre'],
  ['5C-5G', 'Marruecos'],
  ['5H-5I', 'Tanzania'],
  ['5J-5K', 'Colombia'],
  ['5L-5M', 'Liberia'],
  ['5N-5O', 'Nigeria'],
  ['5P-5Q', 'Dinamarca'],
  ['5R-5S', 'Madagascar'],
  ['5T', 'Mauritania'],
  ['5U', 'Níger'],
  ['5V', 'Togo'],
  ['5W', 'Samoa'],
  ['5X', 'Uganda'],
  ['5Y-5Z', 'Kenia'],
  ['6A-6B', 'Egipto'],
  ['6C', 'Siria'],
  ['6D-6J', 'México'],
  ['6K-6N', 'Corea del Sur'],
  ['6O', 'Somalia'],
  ['6P-6S', 'Pakistán'],
  ['6T-6U', 'Sudán'],
  ['6V-6W', 'Senegal'],
  ['6X', 'Madagascar'],
  ['6Y', 'Jamaica'],
  ['6Z', 'Liberia'],
  ['7A-7I', 'Indonesia'],
  ['7J-7N', 'Japón'],
  ['7O', 'Yemen'],
  ['7P', 'Lesoto'],
  ['7Q', 'Malaui'],
  ['7R', 'Argelia'],
  ['7S', 'Suecia'],
  ['7T-7Y', 'Argelia'],
  ['7Z', 'Arabia Saudita'],
  ['8A-8I', 'Indonesia'],
  ['8J-8N', 'Japón'],
  ['8O', 'Botsuana'],
  ['8P', 'Barbados'],
  ['8Q', 'Maldivas'],
  ['8R', 'Guyana'],
  ['8S', 'Suecia'],
  ['8T-8Y', 'India'],
  ['8Z', 'Arabia Saudita'],
  ['9A', 'Croacia'],
  ['9B-9D', 'Irán'],
  ['9E-9F', 'Etiopía'],
  ['9G', 'Ghana'],
  ['9H', 'Malta'],
  ['9I-9J', 'Zambia'],
  ['9K', 'Kuwait'],
  ['9L', 'Sierra Leona'],
  ['9M', 'Malasia'],
  ['9N', 'Nepal'],
  ['9O-9T', 'República Democrática del Congo'],
  ['9U', 'Burundi'],
  ['9V', 'Singapur'],
  ['9W', 'Malasia'],
  ['9X', 'Ruanda'],
  ['9Y-9Z', 'Trinidad y Tobago'],
  ['A2', 'Botsuana'],
  ['A3', 'Tonga'],
  ['A4', 'Omán'],
  ['A5', 'Bután'],
  ['A6', 'Emiratos Árabes Unidos'],
  ['A7', 'Catar'],
  ['A8', 'Liberia'],
  ['A9', 'Baréin'],
  ['AA-AL', 'Estados Unidos'],
  ['AM-AO', 'España'],
  ['AP-AS', 'Pakistán'],
  ['AT-AW', 'India'],
  ['AX', 'Australia'],
  ['AY-AZ', 'Argentina'],
  ['BU-BX', 'Taiwán'],
  ['B', 'China'],
  ['C2', 'Nauru'],
  ['C3', 'Andorra'],
  ['C4', 'Chipre'],
  ['C5', 'Gambia'],
  ['C6', 'Bahamas'],
  ['C8-C9', 'Mozambique'],
  ['CA-CE', 'Chile'],
  ['CF-CK', 'Canadá'],
  ['CL-CM', 'Cuba'],
  ['CN', 'Marruecos'],
  ['CO', 'Cuba'],
  ['CP', 'Bolivia'],
  ['CQ-CU', 'Portugal'],
  ['CV-CX', 'Uruguay'],
  ['CY-CZ', 'Canadá'],
  ['D2-D3', 'Angola'],
  ['D4', 'Cabo Verde'],
  ['D5', 'Liberia'],
  ['D6', 'Comoras'],
  ['D7-D9', 'Corea del Sur'],
  ['DA-DR', 'Alemania'],
  ['DS-DT', 'Corea del Sur'],
  ['DU-DZ', 'Filipinas'],
  ['E2', 'Tailandia'],
  ['E3', 'Eritrea'],
  ['E4', 'Palestina'],
  ['E5', 'Islas Cook'],
  ['E6', 'Niue'],
  ['E7', 'Bosnia y Herzegovina'],
  ['EA-EH', 'España'],
  ['EI-EJ', 'Irlanda'],
  ['EK', 'Armenia'],
  ['EL', 'Liberia'],
  ['EM-EO', 'Ucrania'],
  ['EP-EQ', 'Irán'],
  ['ER', 'Moldavia'],
  ['ES', 'Estonia'],
  ['ET', 'Etiopía'],
  ['EU-EW', 'Bielorrusia'],
  ['EX', 'Kirguistán'],
  ['EY', 'Tayikistán'],
  ['EZ', 'Turkmenistán'],
  ['F', 'Francia'],
  ['G', 'Reino Unido'],
  ['H2', 'Chipre'],
  ['H3', 'Panamá'],
  ['H4', 'Islas Salomón'],
  ['H6-H7', 'Nicaragua'],
  ['H8-H9', 'Panamá'],
  ['HA', 'Hungría'],
  ['HB', 'Suiza'],
  ['HC-HD', 'Ecuador'],
  ['HE', 'Suiza'],
  ['HF', 'Polonia'],
  ['HG', 'Hungría'],
  ['HH', 'Haití'],
  ['HI', 'República Dominicana'],
  ['HJ-HK', 'Colombia'],
  ['HL', 'Corea del Sur'],
  ['HM', 'Corea del Norte'],
  ['HN', 'Irak'],
  ['HO-HP', 'Panamá'],
  ['HQ-HR', 'Honduras'],
  ['HS', 'Tailandia'],
  ['HT', 'Nicaragua'],
  ['HU', 'El Salvador'],
  ['HV', 'Vaticano'],
  ['HW-HY', 'Francia'],
  ['HZ', 'Arabia Saudita'],
  ['I', 'Italia'],
  ['J2', 'Yibuti'],
  ['J3', 'Granada'],
  ['J4', 'Grecia'],
  ['J5', 'Guinea-Bisáu'],
  ['J6', 'Santa Lucía'],
  ['J7', 'Dominica'],
  ['J8', 'San Vicente y las Granadinas'],
  ['JA-JS', 'Japón'],
  ['JT-JV', 'Mongolia'],
  ['JW-JX', 'Noruega'],
  ['JY', 'Jordania'],
  ['JZ', 'Indonesia'],
  ['K', 'Estados Unidos'],
  ['L2-L9', 'Argentina'],
  ['LA-LN', 'Noruega'],
  ['LO-LW', 'Argentina'],
  ['LX', 'Luxemburgo'],
  ['LY', 'Lituania'],
  ['LZ', 'Bulgaria'],
  ['M', 'Reino Unido'],
  ['N', 'Estados Unidos'],
  ['OA-OC', 'Perú'],
  ['OD', 'Líbano'],
  ['OE', 'Austria'],
  ['OF-OJ', 'Finlandia'],
  ['OK-OL', 'República Checa'],
  ['OM', 'Eslovaquia'],
  ['ON-OT', 'Bélgica'],
  ['OU-OZ', 'Dinamarca'],
  ['P2', 'Papúa Nueva Guinea'],
  ['P3', 'Chipre'],
  ['P4', 'Aruba'],
  ['P5-P9', 'Corea del Norte'],
  ['PA-PI', 'Países Bajos'],
  ['PJ', 'Caribe Neerlandés'],
  ['PK-PO', 'Indonesia'],
  ['PP-PY', 'Brasil'],
  ['PZ', 'Surinam'],
  ['R', 'Rusia'],
  ['S2-S3', 'Bangladés'],
  ['S5', 'Eslovenia'],
  ['S6', 'Singapur'],
  ['S7', 'Seychelles'],
  ['S8', 'Sudáfrica'],
  ['S9', 'Santo Tomé y Príncipe'],
  ['SA-SM', 'Suecia'],
  ['SN-SR', 'Polonia'],
  ['SS', 'Egipto'],
  ['ST', 'Sudán'],
  ['SU', 'Egipto'],
  ['SV-SZ', 'Grecia'],
  ['T2', 'Tuvalu'],
  ['T3', 'Kiribati'],
  ['T4', 'Cuba'],
  ['T5', 'Somalia'],
  ['T6', 'Afganistán'],
  ['T7', 'San Marino'],
  ['T8', 'Palaos'],
  ['TA-TC', 'Turquía'],
  ['TD', 'Guatemala'],
  ['TE', 'Costa Rica'],
  ['TF', 'Islandia'],
  ['TG', 'Guatemala'],
  ['TH', 'Francia'],
  ['TI', 'Costa Rica'],
  ['TJ', 'Camerún'],
  ['TK', 'Francia'],
  ['TL', 'República Centroafricana'],
  ['TM', 'Francia'],
  ['TN', 'Congo'],
  ['TO-TQ', 'Francia'],
  ['TR', 'Gabón'],
  ['TS', 'Túnez'],
  ['TT', 'Chad'],
  ['TU', 'Costa de Marfil'],
  ['TV-TX', 'Francia'],
  ['TY', 'Benín'],
  ['TZ', 'Malí'],
  ['UA-UI', 'Rusia'],
  ['UJ-UM', 'Uzbekistán'],
  ['UN-UQ', 'Kazajistán'],
  ['UR-UZ', 'Ucrania'],
  ['V2', 'Antigua y Barbuda'],
  ['V3', 'Belice'],
  ['V4', 'San Cristóbal y Nieves'],
  ['V5', 'Namibia'],
  ['V6', 'Micronesia'],
  ['V7', 'Islas Marshall'],
  ['V8', 'Brunéi'],
  ['VA-VG', 'Canadá'],
  ['VH-VN', 'Australia'],
  ['VO', 'Canadá'],
  ['VP-VQ', 'Reino Unido'],
  ['VR', 'Hong Kong'],
  ['VS', 'Reino Unido'],
  ['VT-VW', 'India'],
  ['VX-VY', 'Canadá'],
  ['VZ', 'Australia'],
  ['W', 'Estados Unidos'],
  ['XA-XI', 'México'],
  ['XJ-XO', 'Canadá'],
  ['XP', 'Dinamarca'],
  ['XQ-XR', 'Chile'],
  ['XS', 'China'],
  ['XT', 'Burkina Faso'],
  ['XU', 'Camboya'],
  ['XV', 'Vietnam'],
  ['XW', 'Laos'],
  ['XX', 'Macao'],
  ['XY-XZ', 'Myanmar'],
  ['Y2-Y9', 'Alemania'],
  ['YA', 'Afganistán'],
  ['YB-YH', 'Indonesia'],
  ['YI', 'Irak'],
  ['YJ', 'Vanuatu'],
  ['YK', 'Siria'],
  ['YL', 'Letonia'],
  ['YM', 'Turquía'],
  ['YN', 'Nicaragua'],
  ['YO-YR', 'Rumania'],
  ['YS', 'El Salvador'],
  ['YT-YU', 'Serbia'],
  ['YV-YY', 'Venezuela'],
  ['YZ', 'Serbia'],
  ['Z2', 'Zimbabue'],
  ['Z3', 'Macedonia del Norte'],
  ['Z6', 'Kosovo'],
  ['Z8', 'Sudán del Sur'],
  ['ZA', 'Albania'],
  ['ZB-ZJ', 'Reino Unido'],
  ['ZK-ZM', 'Nueva Zelanda'],
  ['ZN-ZO', 'Reino Unido'],
  ['ZP', 'Paraguay'],
  ['ZQ', 'Reino Unido'],
  ['ZR-ZU', 'Sudáfrica'],
  ['ZV-ZZ', 'Brasil'],
]

/** The country of a prefix or a call sign, by its first two characters. */
export function countryOf(callSign: string): string | null {
  const head = callSign.slice(0, 2)
  for (const [range, name] of COUNTRIES) {
    const [from, to = from] = range.split('-')
    if (from.length === 1 ? head[0] === from : head.length === 2 && head >= from && head <= to) return name
  }
  return null
}

/** The province of an Argentine call sign without a `/`, such as Buenos Aires for LU1DAB. */
export function provinceOf(callSign: string): string | null {
  const suffix = ARGENTINE.exec(callSign)?.[1]
  if (!suffix) return null
  const split = SPLIT_PROVINCES[suffix[0]]
  if (split) return suffix.length < 2 ? null : suffix[1] <= split.last ? split.first : split.second
  return PROVINCES[suffix[0]] ?? null
}

export const provinceKey = (name: string): string => `AR-${name}`
/** The name of an entity by its key: the key without the "AR-" of a province. */
export const nameOfKey = (key: string): string => key.replace(/^AR-/, '')
const province = (name: string): Entity => ({ key: provinceKey(name), name, kind: 'province' })
const UNKNOWN_PROVINCE: Entity = { key: 'AR-?', name: 'Argentina (provincia sin identificar)', kind: 'province' }
const UNKNOWN: Entity = { key: '?', name: 'Sin identificar', kind: 'country' }

/**
 * The entity of a contact. The longest part between the `/` characters is the base call sign (as in the API).
 * A prefix before it, such as CX in CX/LU1ABC, gives the country. For an Argentine station, a suffix of one
 * letter after it is the province of a portable station, such as A in LU2GAC/A. A suffix G or X does not tell
 * the province: the base call sign tells it.
 */
export function entityOf(callSign: string): Entity {
  const parts = callSign.toUpperCase().replace(/\s+/g, '').split('/')
  const baseIndex = parts.reduce((best, part, index) => (part.length > parts[best].length ? index : best), 0)
  const base = parts[baseIndex]
  const prefix = baseIndex > 0 ? parts[baseIndex - 1] : null
  const country = countryOf(prefix || base)
  if (country === null) return UNKNOWN
  if (country !== 'Argentina') return { key: country, name: country, kind: 'country' }

  const suffix = parts[baseIndex + 1] ?? ''
  if (/^[A-Z]$/.test(suffix) && PROVINCES[suffix]) return province(PROVINCES[suffix])
  // A foreign station in Argentina, such as LU/CX1ABC, has no province.
  const name = prefix && countryOf(base) !== 'Argentina' ? null : provinceOf(base)
  return name ? province(name) : UNKNOWN_PROVINCE
}

/** The contacts with one entity. */
export interface EntityCount {
  entity: Entity
  qsos: number
  /** The different base call signs. */
  participants: number
}

/**
 * The QSOs and the participants of each entity, the most QSOs first.
 *
 * @param contacts Each call sign of a contact, with its base call sign and its number of QSOs.
 */
export function countEntities(contacts: { callSign: string; baseCallSign: string; qsos: number }[]): EntityCount[] {
  const byKey = new Map<string, { entity: Entity; qsos: number; calls: Set<string> }>()
  for (const contact of contacts) {
    const entity = entityOf(contact.callSign)
    const item = byKey.get(entity.key) ?? { entity, qsos: 0, calls: new Set<string>() }
    item.qsos += contact.qsos
    item.calls.add(contact.baseCallSign)
    byKey.set(entity.key, item)
  }
  return [...byKey.values()]
    .map(({ entity, qsos, calls }) => ({ entity, qsos, participants: calls.size }))
    .sort((a, b) => b.qsos - a.qsos || a.entity.name.localeCompare(b.entity.name, 'es'))
}

/** The number of entities, without the contacts that the system cannot place. */
export function entityTotal(counts: EntityCount[]): number {
  return counts.filter(({ entity }) => entity.key !== UNKNOWN.key && entity.key !== UNKNOWN_PROVINCE.key).length
}
