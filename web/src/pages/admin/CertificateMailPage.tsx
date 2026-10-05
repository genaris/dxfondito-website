import { useCallback, useEffect, useState } from 'react'
import { ContactEditor } from '../../components/ContactEditor.tsx'
import { MessageEditor } from '../../components/MessageEditor.tsx'
import {
  markCertificates,
  previewCertificate,
  readCertificateMail,
  resetCertificateMailTemplate,
  saveCertificateMailTemplate,
  sendCertificates,
  sendInBatches,
  skipReason,
  testCertificate,
  unmarkCertificates,
} from '../../mail.ts'
import type { CertificateItem, CertificateKey, CertificateMail } from '../../mail.ts'
import { levelClass } from '../../program.ts'
import { href } from '../../router.ts'
import { DeliveryStatus, EmailCell, SendProgress } from './mailParts.tsx'
import { useSendProgress } from './useSendProgress.ts'

type State = { kind: 'loading' } | { kind: 'ready'; data: CertificateMail } | { kind: 'error' }

const keyOf = (item: CertificateItem): string => `${item.callSign}:${item.points}`
const certificateKey = (item: CertificateItem): CertificateKey => ({ callSign: item.callSign, points: item.points })
const target = (value: string): CertificateKey => {
  const [callSign, points] = value.split(':')
  return { callSign, points: Number(points) }
}

/**
 * The certificate messages of a season (FR-MAIL-12 to FR-MAIL-14). A certificate that went before can go again,
 * after a warning: the administrator decides.
 */
export function CertificateMailPage({ season }: { season: number }) {
  const [state, setState] = useState<State>({ kind: 'loading' })
  const [selected, setSelected] = useState<Set<string>>(new Set())
  const [editing, setEditing] = useState<string | null>(null)
  const [dirty, setDirty] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const progress = useSendProgress()
  const preview = useCallback(
    (subject: string, body: string, value: string) => previewCertificate(season, target(value), subject, body),
    [season],
  )

  const load = useCallback(() => {
    readCertificateMail(season)
      .then((data) => setState({ kind: 'ready', data }))
      .catch(() => setState({ kind: 'error' }))
  }, [season])

  useEffect(load, [load])

  if (state.kind === 'loading') return <p>Cargando…</p>
  if (state.kind === 'error') return <p className="error">No se pudieron leer los certificados.</p>

  const { data } = state
  const items = data.certificates
  const sendable = (item: CertificateItem) =>
    item.date !== null && item.available && item.recipient.email !== null && !item.recipient.noMail
  const pending = items.filter((item) => item.status === 'pending' && sendable(item))
  const chosen = items.filter((item) => selected.has(keyOf(item)))

  function toggle(key: string) {
    setSelected((current) => {
      const next = new Set(current)
      if (next.has(key)) next.delete(key)
      else next.add(key)
      return next
    })
  }

  async function send(list: CertificateItem[], again: boolean) {
    setNotice(null)
    setError(null)
    await progress.run(list.length, (callbacks) =>
      sendInBatches(list.map(certificateKey), (batch) => sendCertificates(season, batch, again), {
        ...callbacks,
        onResult: (result) => callbacks.onResult({ ...result, callSign: result.key }),
      }),
    )
    setSelected(new Set())
    load()
  }

  async function sendChosen() {
    // FR-MAIL-13: a certificate that went before goes again only after the warning.
    const before = chosen.filter((item) => item.last !== null)
    if (before.length > 0) {
      const lines = before.map(
        (item) =>
          `· Ya se envió un certificado ${item.level} ${season} a ${item.callSign} el ${item.last?.at.slice(0, 10)}` +
          (item.last?.certificateDate ? `, con fecha ${item.last.certificateDate}` : '') +
          (item.date && item.date !== item.last?.certificateDate ? `. El de ahora tiene fecha ${item.date}.` : '.'),
      )
      if (!window.confirm(`${lines.join('\n')}\n\n¿Enviarlos de nuevo?`)) return
    }
    await send(chosen, before.length > 0)
  }

  async function mark(list: CertificateItem[]) {
    if (!window.confirm(`¿Marcar ${list.length} certificados como enviados, sin mandar correo?`)) return
    try {
      const result = await markCertificates(season, list.map(certificateKey))
      setNotice(`Se marcaron ${result.marked} certificados como enviados.`)
      setSelected(new Set())
      load()
    } catch {
      setError('No se pudieron marcar.')
    }
  }

  async function unmark(list: CertificateItem[]) {
    try {
      const result = await unmarkCertificates(season, list.map(certificateKey))
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
  const withDate = items.filter((item) => item.date !== null)
  return (
    <>
      <p>
        <a href={href('/admin/envios')}>← Envíos</a>
      </p>
      <h2>Envío de certificados {season}</h2>
      <p className="hint">Un correo por certificado, con el PDF adjunto.</p>
      {!data.configured && (
        <p className="error">El envío no está configurado: falta el servidor de correo en config.php.</p>
      )}

      <MessageEditor
        key={`${data.template.subject}|${data.template.body}`}
        title={`Mensaje de los certificados ${season}`}
        template={data.template}
        variables={data.variables}
        targets={withDate.map((item) => ({
          value: keyOf(item),
          label: `${item.callSign} · ${item.level}${item.recipient.email ? '' : ' (sin e-mail)'}`,
        }))}
        preview={preview}
        test={data.configured ? (subject, body, value) => testCertificate(season, target(value), subject, body) : null}
        save={async (subject, body) => setState({ kind: 'ready', data: await saveCertificateMailTemplate(season, subject, body) })}
        reset={async () => setState({ kind: 'ready', data: await resetCertificateMailTemplate(season) })}
        resetLabel="Volver al mensaje general"
        onDirty={setDirty}
      />

      <section>
        <div className="title-row">
          <h3>Certificados</h3>
          {data.usage && (
            <span className="hint">
              Correos en la última hora: {data.usage.used} de {data.usage.limit}
            </span>
          )}
        </div>
        {items.length === 0 && <p>Nadie alcanzó un certificado en la temporada {season} todavía.</p>}
        {dirty && <p className="error">Guardá o descartá los cambios del mensaje antes de enviar.</p>}
        <div className="actions mail-actions">
          <button type="button" disabled={busy || dirty || !data.configured || pending.length === 0} onClick={() => void send(pending, false)}>
            Enviar pendientes ({pending.length})
          </button>
          <button type="button" disabled={busy || dirty || !data.configured || chosen.length === 0} onClick={() => void sendChosen()}>
            Enviar seleccionados ({chosen.length})
          </button>
          <button
            type="button"
            disabled={busy || chosen.filter((item) => item.date !== null).length === 0}
            onClick={() => void mark(chosen.filter((item) => item.date !== null))}
          >
            Marcar seleccionados como enviados
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

        {items.length > 0 && (
          <div className="table-scroll">
            <table className="table mail-table">
              <thead>
                <tr>
                  <th>
                    <input
                      type="checkbox"
                      aria-label="Seleccionar todos"
                      checked={selected.size === items.length}
                      onChange={(event) => setSelected(event.target.checked ? new Set(items.map(keyOf)) : new Set())}
                    />
                  </th>
                  <th>Indicativo</th>
                  <th>Nivel</th>
                  <th>Fecha</th>
                  <th>E-mail</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) =>
                  editing === keyOf(item) ? (
                    <tr key={keyOf(item)}>
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
                    <tr key={keyOf(item)}>
                      <td>
                        <input
                          type="checkbox"
                          aria-label={`Seleccionar ${item.callSign} ${item.level}`}
                          checked={selected.has(keyOf(item))}
                          onChange={() => toggle(keyOf(item))}
                        />
                      </td>
                      <td className="nowrap">
                        <a href={href(`/participante/${item.callSign}`)}>{item.callSign}</a>
                        {item.name && <span className="hint"> · {item.name}</span>}
                      </td>
                      <td className="nowrap">
                        <span className={levelClass(item.points)} aria-hidden="true" /> {item.level}
                      </td>
                      <td className="nowrap">{item.date ?? <span className="hint">—</span>}</td>
                      <td>
                        <EmailCell recipient={item.recipient} onEdit={() => setEditing(keyOf(item))} />
                      </td>
                      <td>
                        <CertificateStatus item={item} />
                      </td>
                    </tr>
                  ),
                )}
              </tbody>
            </table>
          </div>
        )}
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

function CertificateStatus({ item }: { item: CertificateItem }) {
  switch (item.status) {
    case 'changed':
      return (
        <span className="status status-changed" title="Se envió con otra fecha. Decidí si enviarlo de nuevo.">
          Cambió · se envió con fecha {item.last?.certificateDate}
        </span>
      )
    case 'revoked':
      return (
        <span className="status status-failed" title="Se envió, pero el participante ya no tiene el certificado (se borró un log).">
          Ya no corresponde
        </span>
      )
    case 'unavailable':
      return <span className="status status-pending">Sin plantilla del nivel</span>
    default:
      return <DeliveryStatus pending={item.status === 'pending'} last={item.last} />
  }
}
