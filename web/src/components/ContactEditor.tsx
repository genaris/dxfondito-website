import { useEffect, useState } from 'react'
import { ApiError } from '../api.ts'
import { longDate } from '../program.ts'
import { readContact, saveContact, setInvalidEmail } from '../mail.ts'
import type { ContactCard } from '../mail.ts'

/**
 * The entry of a call sign in the address book (FR-MAIL-3 to FR-MAIL-5): an own address, the mark "no messages",
 * notes, and the addresses that the logs gave, with the mark of a bounced address.
 */
export function ContactEditor({ callSign, onDone, onCancel }: { callSign: string; onDone: () => void; onCancel: () => void }) {
  const [card, setCard] = useState<ContactCard | null>(null)
  const [email, setEmail] = useState('')
  const [noMail, setNoMail] = useState(false)
  const [notes, setNotes] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let active = true
    readContact(callSign)
      .then((data) => {
        if (!active) return
        setCard(data)
        setEmail(data.entry?.email ?? '')
        setNoMail(data.entry?.noMail ?? false)
        setNotes(data.entry?.notes ?? '')
      })
      .catch(() => {
        if (active) setError('No se pudieron leer los datos.')
      })
    return () => {
      active = false
    }
  }, [callSign])

  async function save() {
    setBusy(true)
    setError(null)
    try {
      // The name is for a later version: the QSL cards use the name of the official lists (D-28).
      await saveContact(callSign, { name: card?.entry?.name ?? '', email, noMail, notes })
      onDone()
    } catch (failure) {
      setError(failure instanceof ApiError && failure.status === 422 ? 'El e-mail no es válido.' : 'No se pudo guardar.')
      setBusy(false)
    }
  }

  async function toggleInvalid(address: string, invalid: boolean) {
    setBusy(true)
    try {
      await setInvalidEmail(address, invalid)
      setCard(await readContact(callSign))
    } catch {
      setError('No se pudo cambiar la marca.')
    } finally {
      setBusy(false)
    }
  }

  if (!card) return error ? <p className="error">{error}</p> : <p>Cargando…</p>

  return (
    <div className="contact-editor form">
      <h4>
        {card.callSign}
        {card.officialName && <span className="hint"> · {card.officialName}</span>}
      </h4>
      {card.known.length > 0 ? (
        <div>
          <p className="hint">E-mails de los logs, el más reciente primero:</p>
          <ul className="known-emails">
            {card.known.map((item) => (
              <li key={item.email}>
                <span className={item.invalid ? 'struck' : undefined}>{item.email}</span>
                <span className="hint"> · último QSO {longDate(item.lastQsoAt.slice(0, 10))}</span>
                {item.invalid && <span className="tag">rebotó</span>}{' '}
                <button type="button" className="link" disabled={busy} onClick={() => setEmail(item.email)}>
                  Usar
                </button>{' '}
                <button type="button" className="link" disabled={busy} onClick={() => void toggleInvalid(item.email, !item.invalid)}>
                  {item.invalid ? 'Quitar la marca de rebote' : 'Marcar que rebotó'}
                </button>
              </li>
            ))}
          </ul>
        </div>
      ) : (
        <p className="hint">Los logs no tienen e-mail para este indicativo.</p>
      )}
      <label>
        E-mail de la libreta (reemplaza al de los logs)
        <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} placeholder="nombre@ejemplo.com" />
      </label>
      <label className="checkbox">
        <input type="checkbox" checked={noMail} onChange={(event) => setNoMail(event.target.checked)} />
        No enviarle correos (lo pidió)
      </label>
      <label>
        Notas
        <textarea value={notes} rows={2} maxLength={500} onChange={(event) => setNotes(event.target.value)} />
      </label>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <div className="actions">
        <button type="button" disabled={busy} onClick={() => void save()}>
          Guardar
        </button>
        <button type="button" disabled={busy} onClick={onCancel}>
          Cancelar
        </button>
      </div>
    </div>
  )
}
