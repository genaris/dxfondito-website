import { useCallback, useEffect, useState } from 'react'
import { activityDates, readActivity } from '../activities.ts'
import type { ActivityDetail } from '../activities.ts'
import { ApiError } from '../api.ts'
import { href } from '../router.ts'
import { useSession } from '../useSession.ts'
import { LogsSection } from './LogsSection.tsx'

type State = { kind: 'loading' } | { kind: 'ready'; activity: ActivityDetail } | { kind: 'missing' } | { kind: 'error' }

/**
 * The data of an activity and its operators (FR-PUB-14). The signed-in users also see its logs (FR-LOG-19).
 * The participants come with the ranking.
 */
export function ActivityPage({ id }: { id: number }) {
  const { user } = useSession()
  const [state, setState] = useState<State>({ kind: 'loading' })

  // The page has a new instance for each activity. Thus the first state is 'loading'.
  const load = useCallback(() => {
    readActivity(id)
      .then((activity) => setState({ kind: 'ready', activity }))
      .catch((error: unknown) => {
        setState({ kind: error instanceof ApiError && error.status === 404 ? 'missing' : 'error' })
      })
  }, [id])

  useEffect(load, [load])

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
        <dt>Operadores</dt>
        <dd>{activity.operators.length > 0 ? activity.operators.join(', ') : 'Todavía no hay logs.'}</dd>
      </dl>
      {activity.reference.description && <p className="prewrap">{activity.reference.description}</p>}
      {activity.description && <p className="prewrap">{activity.description}</p>}
      {user && <LogsSection activityId={activity.id} user={user} onChange={load} />}
    </>
  )
}
