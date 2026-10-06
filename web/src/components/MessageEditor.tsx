import { useEffect, useRef, useState } from 'react'
import { ApiError } from '../api.ts'
import { insertAt, unknownVariables } from '../mail.ts'
import type { MessageText, Preview } from '../mail.ts'

export interface PreviewTarget {
  value: string
  label: string
}

/**
 * The editor of the subject and the body of a message, with its variables and a preview with a real participant
 * (FR-MAIL-10, FR-MAIL-11). The text is plain text.
 */
export function MessageEditor({
  title,
  template,
  variables,
  targets,
  preview,
  test,
  save,
  reset,
  resetLabel,
  onDirty,
}: {
  title: string
  template: MessageText
  variables: Record<string, string>
  /** The participants for the preview. */
  targets: PreviewTarget[]
  preview: (subject: string, body: string, target: string) => Promise<Preview>
  test: ((subject: string, body: string, target: string) => Promise<{ to: string }>) | null
  save: (subject: string, body: string) => Promise<void>
  reset: (() => Promise<void>) | null
  resetLabel: string
  onDirty?: (dirty: boolean) => void
}) {
  const [subject, setSubject] = useState(template.subject)
  const [body, setBody] = useState(template.body)
  const [target, setTarget] = useState(targets[0]?.value ?? '')
  const [shown, setShown] = useState<Preview | null>(null)
  const [busy, setBusy] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const lastField = useRef<HTMLInputElement | HTMLTextAreaElement | null>(null)
  const subjectRef = useRef<HTMLInputElement>(null)
  const bodyRef = useRef<HTMLTextAreaElement>(null)

  const dirty = subject !== template.subject || body !== template.body
  const unknown = unknownVariables(`${subject}\n${body}`, variables)

  useEffect(() => {
    onDirty?.(dirty)
  }, [dirty, onDirty])

  // The preview of the API, a moment after the last change.
  useEffect(() => {
    if (!target) return
    let active = true
    const timer = setTimeout(() => {
      preview(subject, body, target)
        .then((result) => {
          if (active) setShown(result)
        })
        .catch(() => {
          if (active) setShown(null)
        })
    }, 400)
    return () => {
      active = false
      clearTimeout(timer)
    }
  }, [subject, body, target, preview])

  function insert(name: string) {
    const field = lastField.current ?? bodyRef.current
    if (!field) return
    const isSubject = field === subjectRef.current
    const text = isSubject ? subject : body
    const result = insertAt(text, field.selectionStart ?? text.length, field.selectionEnd ?? text.length, `{${name}}`)
    if (isSubject) setSubject(result.text)
    else setBody(result.text)
    requestAnimationFrame(() => {
      field.focus()
      field.setSelectionRange(result.cursor, result.cursor)
    })
  }

  async function run(action: () => Promise<string | null>) {
    setBusy(true)
    setNotice(null)
    setError(null)
    try {
      setNotice(await action())
    } catch (failure) {
      setError(failure instanceof ApiError ? apiMessage(failure) : 'No se pudo completar la acción.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="message-editor">
      <div className="title-row">
        <h3>{title}</h3>
        {template.own && <span className="hint">Texto propio</span>}
      </div>
      <div className="message-layout">
        <div className="form message-form">
          <label>
            Asunto
            <input
              ref={subjectRef}
              value={subject}
              maxLength={255}
              onFocus={(event) => (lastField.current = event.currentTarget)}
              onChange={(event) => setSubject(event.target.value)}
            />
          </label>
          <label>
            Mensaje (texto plano)
            <textarea
              ref={bodyRef}
              value={body}
              rows={12}
              maxLength={10000}
              onFocus={(event) => (lastField.current = event.currentTarget)}
              onChange={(event) => setBody(event.target.value)}
            />
          </label>
          {unknown.length > 0 && (
            <p className="error" role="alert">
              Variables desconocidas: {unknown.map((name) => `{${name}}`).join(', ')}. Corregilas antes de guardar.
            </p>
          )}
          <div className="actions">
            <button
              type="button"
              disabled={busy || !dirty || unknown.length > 0}
              onClick={() =>
                void run(async () => {
                  await save(subject, body)
                  return 'Se guardó el mensaje.'
                })
              }
            >
              Guardar el mensaje
            </button>
            {dirty && (
              <button
                type="button"
                className="secondary"
                disabled={busy}
                onClick={() => {
                  setSubject(template.subject)
                  setBody(template.body)
                }}
              >
                Descartar los cambios
              </button>
            )}
            {reset && template.own && !dirty && (
              <button
                type="button"
                className="secondary"
                disabled={busy}
                onClick={() =>
                  void run(async () => {
                    await reset()
                    return null
                  })
                }
              >
                {resetLabel}
              </button>
            )}
          </div>
          {notice && <p role="status">{notice}</p>}
          {error && (
            <p className="error" role="alert">
              {error}
            </p>
          )}
        </div>
        <aside className="variables" aria-label="Variables">
          <h4>Variables</h4>
          <p className="hint">Un clic la inserta en el asunto o el mensaje.</p>
          <ul>
            {Object.entries(variables).map(([name, description]) => (
              <li key={name}>
                <button type="button" className="chip" title={description} onClick={() => insert(name)}>
                  {`{${name}}`}
                </button>
                <span className="hint"> {description}</span>
              </li>
            ))}
          </ul>
        </aside>
      </div>

      {targets.length > 0 && (
        <div className="message-preview">
          <div className="title-row">
            <h4>Vista previa</h4>
            <label className="inline-field">
              Para{' '}
              <select value={target} onChange={(event) => setTarget(event.target.value)}>
                {targets.map((item) => (
                  <option key={item.value} value={item.value}>
                    {item.label}
                  </option>
                ))}
              </select>
            </label>
          </div>
          {shown && (
            <div className="mail-sample">
              <p>
                <strong>Para:</strong> {shown.to ?? <span className="hint">sin e-mail</span>}
              </p>
              <p>
                <strong>Asunto:</strong> {shown.subject}
              </p>
              <pre className="mail-body">{shown.text}</pre>
              <p className="hint">
                Adjuntos: {shown.attachments.length > 0 ? shown.attachments.join(', ') : 'ninguno'}
              </p>
            </div>
          )}
          {test && (
            <button
              type="button"
              className="secondary"
              disabled={busy || unknown.length > 0 || !target}
              onClick={() =>
                void run(async () => {
                  const result = await test(subject, body, target)
                  return `Se envió la prueba a ${result.to}.`
                })
              }
            >
              Enviarme esta prueba
            </button>
          )}
        </div>
      )}
    </section>
  )
}

function apiMessage(failure: ApiError): string {
  if (failure.status === 422 && failure.message.includes('e-mail address')) return 'Tu cuenta no tiene e-mail. Cargalo en Cuentas.'
  if (failure.status === 422 && failure.message.startsWith('Unknown variables')) return 'El texto tiene variables desconocidas.'
  if (failure.status === 422) return 'El texto no es válido. Revisá el asunto y el mensaje.'
  if (failure.status === 503) return 'El envío no está configurado: falta el servidor de correo en config.php.'
  if (failure.status === 502) return `El servidor de correo no aceptó el mensaje (${failure.message}).`
  return 'No se pudo completar la acción.'
}
