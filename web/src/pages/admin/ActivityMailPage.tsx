import { useCallback, useEffect, useState } from 'react'
import { activityDates } from '../../activities.ts'
import { ContactEditor } from '../../components/ContactEditor.tsx'
import { MessageEditor } from '../../components/MessageEditor.tsx'
import {
  markQsl,
  previewQsl,
  readActivityMail,
  resetActivityTemplate,
  saveActivityTemplate,
  sendInBatches,
  sendQsl,
  skipReason,
  testQsl,
  unmarkQsl,
} from '../../mail.ts'
import type { ActivityMail, QslParticipant } from '../../mail.ts'
import { href } from '../../router.ts'
import { DeliveryStatus, EmailCell, SendProgress } from './mailParts.tsx'
import { useSendProgress } from './useSendProgress.ts'

type State = { kind: 'loading' } | { kind: 'ready'; data: ActivityMail } | { kind: 'error' }

/**
 * The QSL messages of an activity (FR-MAIL-6 to FR-MAIL-11, FR-MAIL-14): the text of the message, the state of
 * each participant, the sending in batches, and the manual marks. Only for administrators.
 */
export function ActivityMailPage({ id }: { id: number }) {
  const [state, setState] = useState<State>({ kind: 'loading' })
  const [selected, setSelected] = useState<Set<string>>(new Set())
  const [editing, setEditing] = useState<string | null>(null)
  const [dirty, setDirty] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const progress = useSendProgress()
  // The preview function must stay the same between renders: the editor asks again after each change.
  const preview = useCallback((subject: string, body: string, target: string) => previewQsl(id, target, subject, body), [id])

  const load = useCallback(() => {
    readActivityMail(id)
      .then((data) => setState({ kind: 'ready', data }))
      .catch(() => setState({ kind: 'error' }))
  }, [id])

  useEffect(load, [load])

  if (state.kind === 'loading') return <p>Cargando…</p>
  if (state.kind === 'error') return <p className="error">No se pudieron leer los envíos de la actividad.</p>

  const { data } = state
  const { activity, participants } = data
  const sendable = (item: QslParticipant) => item.recipient.email !== null && !item.recipient.noMail && item.cards > 0
  const pending = participants.filter((item) => item.status !== 'sent' && sendable(item))
  const chosen = participants.filter((item) => selected.has(item.callSign))

  function toggle(callSign: string) {
    setSelected((current) => {
      const next = new Set(current)
      if (next.has(callSign)) next.delete(callSign)
      else next.add(callSign)
      return next
    })
  }

  async function send(items: QslParticipant[], resend: boolean) {
    setNotice(null)
    setError(null)
    await progress.run(items.length, (callbacks) =>
      sendInBatches(
        items.map((item) => item.callSign),
        (batch) => sendQsl(id, batch, resend),
        callbacks,
      ),
    )
    setSelected(new Set())
    load()
  }

  async function sendChosen() {
    const sent = chosen.filter((item) => item.status === 'sent')
    if (
      sent.length > 0 &&
      !window.confirm(
        `${sent.map((item) => item.callSign).join(', ')} ya recibieron sus QSL. ¿Enviarles de nuevo todas sus QSL?`,
      )
    ) {
      return
    }
    await send(chosen, sent.length > 0)
  }

  async function mark(items: QslParticipant[]) {
    if (!window.confirm(`¿Marcar ${items.length} participantes como enviados, sin mandarles correo?`)) return
    try {
      const result = await markQsl(
        id,
        items.map((item) => item.callSign),
      )
      setNotice(`Se marcaron ${result.marked} participantes como enviados.`)
      setSelected(new Set())
      load()
    } catch {
      setError('No se pudieron marcar.')
    }
  }

  async function unmark(items: QslParticipant[]) {
    try {
      const result = await unmarkQsl(
        id,
        items.map((item) => item.callSign),
      )
      setNotice(
        result.unmarked > 0
          ? `Se quitaron ${result.unmarked} marcas manuales.`
          : 'Los seleccionados no tenían marcas manuales. Un correo enviado no se puede desmarcar.',
      )
      setSelected(new Set())
      load()
    } catch {
      setError('No se pudieron desmarcar.')
    }
  }

  const busy = progress.running
  return (
    <>
      <p>
        <a href={href('/admin/envios')}>← Envíos</a>
      </p>
      <h2>
        Envío de QSL · {activity.reference.code} {activity.reference.name}
      </h2>
      <p className="hint">
        {activityDates(activity)} · {participants.length} participantes · Un correo por participante, con todas sus QSL
        de esta actividad.
      </p>
      {!data.configured && (
        <p className="error">El envío no está configurado: falta el servidor de correo en config.php.</p>
      )}

      <MessageEditor
        key={`${data.template.subject}|${data.template.body}`}
        title="Mensaje de esta actividad"
        template={data.template}
        variables={data.variables}
        targets={participants.map((item) => ({
          value: item.callSign,
          label: `${item.callSign}${item.name ? ` · ${item.name}` : ''}${item.recipient.email ? '' : ' (sin e-mail)'}`,
        }))}
        preview={preview}
        test={data.configured ? (subject, body, target) => testQsl(id, target, subject, body) : null}
        save={async (subject, body) => setState({ kind: 'ready', data: await saveActivityTemplate(id, subject, body) })}
        reset={async () => setState({ kind: 'ready', data: await resetActivityTemplate(id) })}
        resetLabel="Volver al mensaje general"
        onDirty={setDirty}
      />

      <section>
        <div className="title-row">
          <h3>Participantes</h3>
          {data.usage && (
            <span className="hint">
              Correos en la última hora: {data.usage.used} de {data.usage.limit}
            </span>
          )}
        </div>
        {dirty && <p className="error">Guardá o descartá los cambios del mensaje antes de enviar.</p>}
        <div className="actions mail-actions">
          <button type="button" disabled={busy || dirty || !data.configured || pending.length === 0} onClick={() => void send(pending, false)}>
            Enviar pendientes ({pending.length})
          </button>
          <button type="button" disabled={busy || dirty || !data.configured || chosen.length === 0} onClick={() => void sendChosen()}>
            Enviar seleccionados ({chosen.length})
          </button>
          <button type="button" disabled={busy || chosen.length === 0} onClick={() => void mark(chosen)}>
            Marcar seleccionados como enviados
          </button>
          <button
            type="button"
            disabled={busy || participants.every((item) => item.status === 'sent')}
            onClick={() => void mark(participants.filter((item) => item.status !== 'sent'))}
          >
            Marcar todos como enviados
          </button>
          <button type="button" disabled={busy || chosen.length === 0} onClick={() => void unmark(chosen)}>
            Desmarcar seleccionados
          </button>
        </div>
        <SendProgress progress={progress} />
        {notice && <p role="status">{notice}</p>}
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}

        <div className="table-scroll">
          <table className="table mail-table">
            <thead>
              <tr>
                <th>
                  <input
                    type="checkbox"
                    aria-label="Seleccionar todos"
                    checked={participants.length > 0 && selected.size === participants.length}
                    onChange={(event) =>
                      setSelected(event.target.checked ? new Set(participants.map((item) => item.callSign)) : new Set())
                    }
                  />
                </th>
                <th>Indicativo</th>
                <th>Nombre</th>
                <th>E-mail</th>
                <th>QSL</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              {participants.map((item) =>
                editing === item.callSign ? (
                  <tr key={item.callSign}>
                    <td colSpan={6}>
                      <ContactEditor
                        callSign={item.callSign}
                        onDone={() => {
                          setEditing(null)
                          load()
                        }}
                        onCancel={() => setEditing(null)}
                      />
                    </td>
                  </tr>
                ) : (
                  <tr key={item.callSign}>
                    <td>
                      <input
                        type="checkbox"
                        aria-label={`Seleccionar ${item.callSign}`}
                        checked={selected.has(item.callSign)}
                        onChange={() => toggle(item.callSign)}
                      />
                    </td>
                    <td className="nowrap">
                      <a href={href(`/participante/${item.callSign}`)}>{item.callSign}</a>
                    </td>
                    <td>{item.name || <span className="hint">—</span>}</td>
                    <td>
                      <EmailCell recipient={item.recipient} onEdit={() => setEditing(item.callSign)} />
                    </td>
                    <td className="nowrap">
                      {item.cards === item.contacts ? (
                        item.cards
                      ) : (
                        <span title="Un operador no tiene plantilla QSL para esta actividad">
                          {item.cards} de {item.contacts}
                        </span>
                      )}
                    </td>
                    <td>
                      {item.status === 'new' ? (
                        <span className="status status-new">QSL nuevas ({item.newCards})</span>
                      ) : (
                        <DeliveryStatus pending={item.status === 'pending'} last={item.last} />
                      )}
                      {item.cards === 0 && <span className="hint"> · sin QSL</span>}
                    </td>
                  </tr>
                ),
              )}
            </tbody>
          </table>
        </div>
        {progress.log.length > 0 && (
          <ul className="send-log">
            {progress.log.map((line) => (
              <li key={line.key}>
                {line.callSign}: {line.status === 'failed' ? `falló (${line.error})` : skipReason(line.reason)}
              </li>
            ))}
          </ul>
        )}
      </section>
    </>
  )
}
