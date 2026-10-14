import { useId } from 'react'
import type { EntityCount } from '../entities.ts'
import { MAP } from '../mapShapes.ts'

/**
 * The provinces of Argentina and its neighbours (EntityChart). A contacted entity has the colour of its bar, the
 * others are grey. The inset has the Antarctic sector and the islands of the South Atlantic. The Ciudad de Buenos
 * Aires is too small for the map: a circle marks it.
 */
export function EntityMap({
  counts,
  active,
  onActive,
}: {
  counts: Map<string, EntityCount>
  active: string | null
  onActive: (key: string | null) => void
}) {
  const clip = useId()
  const fill = (key: string) => {
    const count = counts.get(key)
    return count ? `entity-${count.entity.kind}` : 'map-empty'
  }
  const handlers = (key: string) => ({
    onMouseEnter: () => onActive(key),
    onMouseLeave: () => onActive(null),
  })
  const regionClass = (key: string) => `map-region ${fill(key)}${active === key ? ' is-active' : ''}`
  const { inset } = MAP
  const caba = 'AR-Ciudad de Buenos Aires'

  return (
    <svg className="entity-map" viewBox={`0 0 ${MAP.width} ${MAP.height}`} role="img" aria-label="Mapa de las provincias y los países contactados">
      {MAP.regions.map((region, index) => (
        <path key={index} d={region.d} className={regionClass(region.key)} {...handlers(region.key)} />
      ))}
      {MAP.labels.map((label) => (
        <text key={label.text} x={label.x} y={label.y} className="map-label" aria-hidden="true">
          {label.text}
        </text>
      ))}
      <circle cx={MAP.caba.x} cy={MAP.caba.y} r={5} className={regionClass(caba)} {...handlers(caba)} />

      <rect x={inset.x} y={inset.y} width={inset.size} height={inset.size} rx={4} className="map-inset" />
      <clipPath id={clip}>
        <path d={inset.sector} />
      </clipPath>
      <path d={inset.land} className="map-region map-empty map-static" />
      <g {...handlers(inset.key)}>
        <path d={inset.sector} className="map-sector-area" />
        <path d={inset.land} clipPath={`url(#${clip})`} className={regionClass(inset.key)} />
      </g>
      <path d={inset.sector} className="map-sector" aria-hidden="true" />
    </svg>
  )
}
