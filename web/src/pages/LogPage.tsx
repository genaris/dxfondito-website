import { useEffect, useState } from 'react'
import { ApiError } from '../api.ts'
import { formatUtc } from '../audit.ts'
import { frequencyText, logFileUrl, readLog } from '../logs.ts'
import type { Contact, Log } from '../logs.ts'
import { href } from '../router.ts'

type State =
  | { kind: 'loading' }
  | { kind: 'ready'; log: Log & { contacts: Contact[] } }
  | { kind: 'missing' }
  | { kind: 'error' }

/**
 * The contacts of a log, for the signed-in users (FR-LOG-21).
 */
export function LogPage({ id }: { id: number }) {
  const [state, setState] = useState<State>({ kind: 'loading' })

  useEffect(() => {
    // The page has a new instance for each log. Thus the first state is 'loading'.
    let active = true
    readLog(id)
      .then((log) => {
        if (active) setState({ kind: 'ready', log })
      })
      .catch((error: unknown) => {
        if (active) setState({ kind: error instanceof ApiError && error.status === 404 ? 'missing' : 'error' })
      })
    return () => {
      active = false
    }
  }, [id])

  if (state.kind === 'loading') return <p>Cargando…</p>
  if (state.kind === 'missing') return <p>El log no existe.</p>
  if (state.kind === 'error') return <p className="error">No se pudo leer el log.</p>

  const { log } = state
  return (
    <>
      <p>
        <a href={href(`/actividad/${log.activityId}`)}>← Actividad</a>
      </p>
      <h2>{log.fileName}</h2>
      <dl className="facts">
        <dt>Operador</dt>
        <dd>{log.operator.callSign}</dd>
        <dt>Subido</dt>
        <dd>
          {formatUtc(log.uploadedAt)}
          {log.uploadedBy.id !== log.operator.id && ` por ${log.uploadedBy.callSign}`}
        </dd>
        <dt>Contactos</dt>
        <dd>{log.contactCount}</dd>
      </dl>
      {log.canManage && (
        <p>
          <a href={logFileUrl(log.id)} download={log.fileName}>
            Descargar el archivo original
          </a>
        </p>
      )}
      <div className="table-scroll">
        <table className="table">
          <thead>
            <tr>
              <th>Fecha y hora (UTC)</th>
              <th>Indicativo</th>
              <th>Nombre</th>
              <th>Frecuencia</th>
              <th>Modo</th>
              <th>RST enviado</th>
              <th>RST recibido</th>
            </tr>
          </thead>
          <tbody>
            {log.contacts.map((contact, index) => (
              <tr key={index}>
                <td className="nowrap">{contact.qsoAt.slice(0, 10)} {contact.qsoAt.slice(11, 16)}</td>
                <td>{contact.callSign}</td>
                <td>{contact.name}</td>
                <td className="nowrap">{frequencyText(contact)}</td>
                <td>{contact.mode}</td>
                <td>{contact.rstSent}</td>
                <td>{contact.rstRcvd}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  )
}
