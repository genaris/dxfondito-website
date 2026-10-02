import { useCallback, useEffect, useState } from 'react'
import { activityDates, readActivity } from '../activities.ts'
import type { ActivityDetail } from '../activities.ts'
import { ApiError } from '../api.ts'
import { frequencyText } from '../logs.ts'
import { qslCardUrl, qslFileName } from '../qsl.ts'
import { href } from '../router.ts'
import { useSession } from '../useSession.ts'
import { LogsSection } from './LogsSection.tsx'
import { QslTemplatesSection } from './QslTemplatesSection.tsx'

type State = { kind: 'loading' } | { kind: 'ready'; activity: ActivityDetail } | { kind: 'missing' } | { kind: 'error' }

/**
 * The data of an activity, its operators and all its contacts (FR-PUB-14, FR-PUB-15).
 * The signed-in users also see its logs (FR-LOG-19) and its QSL card templates, above the contacts:
 * the list of contacts can be long.
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
        <dt>Participantes</dt>
        <dd>{activity.participantCount}</dd>
        <dt>QSOs</dt>
        <dd>{activity.contacts.length}</dd>
      </dl>
      {activity.reference.description && <p className="prewrap">{activity.reference.description}</p>}
      {activity.description && <p className="prewrap">{activity.description}</p>}
      {user && <LogsSection activityId={activity.id} user={user} onChange={load} />}
      {user && <QslTemplatesSection activityId={activity.id} user={user} />}
      <section>
        <h3>Contactos</h3>
        {activity.contacts.length === 0 ? (
          <p>Todavía no hay contactos.</p>
        ) : (
          <div className="table-scroll">
            <table className="table">
              <thead>
                <tr>
                  <th>Fecha y hora (UTC)</th>
                  <th>Indicativo</th>
                  <th>Operador</th>
                  <th>Frecuencia</th>
                  <th>Modo</th>
                  <th>QSL</th>
                </tr>
              </thead>
              <tbody>
                {activity.contacts.map((contact) => (
                  <tr key={contact.id}>
                    <td className="nowrap">
                      {contact.qsoAt.slice(0, 10)} {contact.qsoAt.slice(11, 16)}
                    </td>
                    <td>
                      <a href={href(`/participante/${contact.baseCallSign}`)}>{contact.callSign}</a>
                    </td>
                    <td>{contact.operator}</td>
                    <td className="nowrap">{frequencyText(contact)}</td>
                    <td>{contact.mode}</td>
                    <td className="nowrap">
                      {contact.qsl ? (
                        <a
                          className="qsl-link"
                          href={qslCardUrl(contact.baseCallSign, contact.id)}
                          download={qslFileName(contact.baseCallSign, activity.reference.code, contact.qsoAt)}
                        >
                          Descargar
                        </a>
                      ) : (
                        <span className="hint">No disponible</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </>
  )
}
