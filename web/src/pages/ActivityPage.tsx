import { useEffect, useState } from 'react'
import { activityDates, readActivity } from '../activities.ts'
import type { Activity } from '../activities.ts'
import { ApiError } from '../api.ts'
import { href } from '../router.ts'

type State = { kind: 'loading' } | { kind: 'ready'; activity: Activity } | { kind: 'missing' } | { kind: 'error' }

/**
 * The data of an activity (FR-PUB-14). The operators and the participants come with the logs.
 */
export function ActivityPage({ id }: { id: number }) {
  const [state, setState] = useState<State>({ kind: 'loading' })

  useEffect(() => {
    // The page has a new instance for each activity. Thus the first state is 'loading'.
    let active = true
    readActivity(id)
      .then((activity) => {
        if (active) setState({ kind: 'ready', activity })
      })
      .catch((error: unknown) => {
        if (active) setState({ kind: error instanceof ApiError && error.status === 404 ? 'missing' : 'error' })
      })
    return () => {
      active = false
    }
  }, [id])

  if (state.kind === 'loading') return <p>Cargando…</p>
  if (state.kind === 'missing') return <p>La actividad no existe.</p>
  if (state.kind === 'error') return <p className="error">No se pudo leer la actividad.</p>

  const { activity } = state
  return (
    <>
      <p>
        <a href={href(`/actividades/${activity.season}`)}>← Actividades de {activity.season}</a>
      </p>
      <h2>
        {activity.reference.code} · {activity.reference.name}
      </h2>
      <dl className="facts">
        <dt>Fechas (UTC)</dt>
        <dd>{activityDates(activity)}</dd>
        <dt>Temporada</dt>
        <dd>{activity.season}</dd>
      </dl>
      {activity.reference.description && <p className="prewrap">{activity.reference.description}</p>}
      {activity.description && <p className="prewrap">{activity.description}</p>}
    </>
  )
}
