import { useEffect, useState } from 'react'
import { activitySchedule, readActivities, readSeasons } from '../activities.ts'
import type { ActivitySummary, Seasons } from '../activities.ts'
import { href, navigate } from '../router.ts'
import { SeasonSelect } from '../SeasonSelect.tsx'
import { useSession } from '../useSession.ts'

type State = { kind: 'loading' } | { kind: 'ready'; activities: ActivitySummary[] } | { kind: 'error' }

/**
 * The activities of a season, the most recent first (FR-PUB-13, FR-PUB-13a, FR-PUB-13b).
 * A visitor sees the activities with at least one contact: the next activities are on the home page and in the
 * calendar of the program. A signed-in user sees all activities, because an operator uploads the logs of an
 * activity without contacts.
 * Without a season in the path, the page shows the current season.
 */
export function ActivitiesPage({ season }: { season: number | null }) {
  const { user } = useSession()
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

  // The filter is at render time: a sign-in or a sign-out changes the list without a new request.
  const shown =
    state.kind !== 'ready'
      ? []
      : user
        ? state.activities
        : state.activities.filter((activity) => activity.contactCount > 0)

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
      {state.kind === 'ready' && shown.length === 0 && (
        <p>
          La temporada {selected} todavía no tiene {user ? 'actividades' : 'actividades con contactos'}. Las próximas
          fechas están en{' '}
          <a href={href('/programa')}>El programa</a>.
        </p>
      )}
      {state.kind === 'ready' && shown.length > 0 && (
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>Referencia</th>
                <th>Nombre</th>
                <th>Fecha y horario</th>
                <th className="number">QSOs</th>
              </tr>
            </thead>
            <tbody>
              {shown.map((activity) => (
                <tr key={activity.id}>
                  <td className="nowrap">
                    <a href={href(`/actividad/${activity.id}`)}>{activity.reference.code}</a>
                  </td>
                  <td>{activity.reference.name}</td>
                  <td>{activitySchedule(activity)}</td>
                  <td className="number">{activity.contactCount}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}
