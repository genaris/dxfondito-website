import { useState } from 'react'
import { entityTotal, nameOfKey } from '../entities.ts'
import type { EntityCount } from '../entities.ts'
import { EntityMap } from './EntityMap.tsx'

const plural = (count: number, one: string, many: string) => `${count} ${count === 1 ? one : many}`

/**
 * The entities of the contacts as horizontal bars: one bar for each province of Argentina and each other country,
 * with its number of QSOs. A province and a country have different colours, and the line of the totals is the
 * legend. The tooltip of a bar also gives its participants.
 * The map at the left has the same colours. The pointer on a region marks its bar, and the pointer or the focus on
 * a bar marks its region.
 */
export function EntityChart({ title, counts }: { title: string; counts: EntityCount[] }) {
  const [active, setActive] = useState<string | null>(null)
  if (counts.length === 0) return null
  const byKey = new Map(counts.map((count) => [count.entity.key, count]))
  const activeCount = active ? byKey.get(active) : undefined
  const max = Math.max(...counts.map((count) => count.qsos))
  const total = entityTotal(counts)
  const provinces = counts.filter(({ entity }) => entity.kind === 'province' && !entity.key.endsWith('?')).length
  const countries = total - provinces

  return (
    <section className="entity-chart">
      <h3>{title}</h3>
      <p className="entity-total">
        <strong>{plural(total, 'entidad', 'entidades')}</strong>
        <span className="hint">
          {' '}
          · <span className="entity-swatch entity-province" aria-hidden="true" />
          {plural(provinces, 'provincia', 'provincias')} y{' '}
          <span className="entity-swatch entity-country" aria-hidden="true" />
          {plural(countries, 'país', 'países')}
        </span>
      </p>
      <div className="entity-layout">
      <figure className="entity-figure">
        <EntityMap counts={byKey} active={active} onActive={setActive} />
        <figcaption className="hint" aria-live="polite">
          {active === null
            ? 'Pasá el mouse o tocá una provincia o un país.'
            : activeCount
              ? `${activeCount.entity.name}: ${plural(activeCount.qsos, 'QSO', 'QSOs')}`
              : `${nameOfKey(active)}: sin contactos`}
        </figcaption>
      </figure>
      <ul className={`entity-bars${activeCount ? ' has-active' : ''}`} aria-label={`QSOs por entidad: ${title}`}>
        {counts.map(({ entity, qsos, participants }) => {
          const kind = entity.kind === 'country' ? 'País' : 'Provincia'
          const detail = `${entity.name} (${kind.toLowerCase()}): ${plural(qsos, 'QSO', 'QSOs')}, ${plural(participants, 'participante', 'participantes')}`
          return (
            <li
              key={entity.key}
              tabIndex={0}
              aria-label={detail}
              className={active === entity.key ? 'is-active' : undefined}
              onMouseEnter={() => setActive(entity.key)}
              onMouseLeave={() => setActive(null)}
              onFocus={() => setActive(entity.key)}
              onBlur={() => setActive(null)}
            >
              <span className="entity-name" aria-hidden="true">
                {entity.name}
              </span>
              <span className="entity-track" aria-hidden="true">
                <span className={`entity-bar entity-${entity.kind}`} style={{ width: `calc((100% - 3rem) * ${qsos / max})` }} />
                <span className="entity-value">{qsos}</span>
              </span>
              <span className="entity-tip" role="tooltip" aria-hidden="true">
                <strong>{entity.name}</strong> <span className="hint">· {kind}</span>
                <br />
                {plural(qsos, 'QSO', 'QSOs')} · {plural(participants, 'participante', 'participantes')}
              </span>
            </li>
          )
        })}
      </ul>
      </div>
    </section>
  )
}
