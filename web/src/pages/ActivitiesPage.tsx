import { useEffect, useState } from 'react'
import { activityDates, readActivities, readSeasons } from '../activities.ts'
import type { Activity, Seasons } from '../activities.ts'
import { href, navigate } from '../router.ts'
import { SeasonSelect } from '../SeasonSelect.tsx'

type State = { kind: 'loading' } | { kind: 'ready'; activities: Activity[] } | { kind: 'error' }

/**
 * The activities of a season, the most recent first (FR-PUB-13, FR-PUB-13a).
 * Without a season in the path, the page shows the current season.
 */
export function ActivitiesPage({ season }: { season: number | null }) {
  const [seasons, setSeasons] = useState<Seasons | null>(null)
  const [state, setState] = useState<State>({ kind: 'loading' })
  const selected = season ?? seasons?.current ?? null

  useEffect(() => {
    readSeasons()
      .then(setSeasons)
      .catch(() => setState({ kind: 'error' }))
  }, [])

  useEffect(() => {
    if (selected === null) return
    // The page has a new instance for each season in the path. Thus the first state is 'loading'.
    let active = true
    readActivities(selected)
      .then((activities) => {
        if (active) setState({ kind: 'ready', activities })
      })
      .catch(() => {
        if (active) setState({ kind: 'error' })
      })
    return () => {
      active = false
    }
  }, [selected])

  const options = seasons && selected !== null && !seasons.seasons.includes(selected)
    ? [selected, ...seasons.seasons]
    : (seasons?.seasons ?? [])

  return (
    <>
      <div className="title-row">
        <h2>Actividades</h2>
        {selected !== null && options.length > 0 && (
          <SeasonSelect seasons={options} value={selected} onChange={(value) => navigate(`/actividades/${value}`)} />
        )}
      </div>
      {state.kind === 'loading' && <p>Cargando…</p>}
      {state.kind === 'error' && <p className="error">No se pudo leer la lista de actividades.</p>}
      {state.kind === 'ready' && state.activities.length === 0 && <p>La temporada {selected} no tiene actividades.</p>}
      {state.kind === 'ready' && state.activities.length > 0 && (
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>Referencia</th>
                <th>Nombre</th>
                <th>Fechas (UTC)</th>
              </tr>
            </thead>
            <tbody>
              {state.activities.map((activity) => (
                <tr key={activity.id}>
                  <td className="nowrap">
                    <a href={href(`/actividad/${activity.id}`)}>{activity.reference.code}</a>
                  </td>
                  <td>{activity.reference.name}</td>
                  <td>{activityDates(activity)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}
