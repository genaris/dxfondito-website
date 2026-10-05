import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { formatUtc } from '../../audit.ts'
import { ContactEditor } from '../../components/ContactEditor.tsx'
import { deleteContact, readAddressBook } from '../../mail.ts'
import type { AddressBookRow } from '../../mail.ts'
import { baseCallSign } from '../../ranking.ts'
import { href } from '../../router.ts'

type State = { kind: 'loading' } | { kind: 'ready'; rows: AddressBookRow[] } | { kind: 'error' }

/**
 * The address book (FR-MAIL-3 to FR-MAIL-5): the own addresses, the marks "no messages" and the notes.
 * The addresses of the logs need no entry: the book is for corrections.
 */
export function AddressBookPage() {
  const [state, setState] = useState<State>({ kind: 'loading' })
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(() => {
    readAddressBook()
      .then((rows) => setState({ kind: 'ready', rows }))
      .catch(() => setState({ kind: 'error' }))
  }, [])

  useEffect(load, [load])

  function open(event: FormEvent) {
    event.preventDefault()
    const callSign = baseCallSign(search)
    if (/^[A-Z0-9]{3,20}$/.test(callSign)) {
      setEditing(callSign)
      setSearch('')
    }
  }

  async function remove(callSign: string) {
    if (!window.confirm(`¿Borrar la entrada de ${callSign}? Vuelve a usar el e-mail de los logs.`)) return
    try {
      await deleteContact(callSign)
      load()
    } catch {
      setError('No se pudo borrar.')
    }
  }

  return (
    <>
      <p>
        <a href={href('/admin/envios')}>← Envíos</a>
      </p>
      <h2>Libreta de contactos</h2>
      <p className="hint">
        El e-mail de cada participante sale del log más reciente. La libreta lo reemplaza, por ejemplo con un dato que
        llegó por otro medio, y permite marcar a quien pidió no recibir correos.
      </p>
      <form className="inline-form" onSubmit={open}>
        <label className="inline-field">
          Indicativo{' '}
          <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="LU1ABC" />
        </label>
        <button type="submit">Abrir</button>
      </form>
      {editing && (
        <ContactEditor
          key={editing}
          callSign={editing}
          onDone={() => {
            setEditing(null)
            load()
          }}
          onCancel={() => setEditing(null)}
        />
      )}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {state.kind === 'loading' && <p>Cargando…</p>}
      {state.kind === 'error' && <p className="error">No se pudo leer la libreta.</p>}
      {state.kind === 'ready' && state.rows.length === 0 && <p>La libreta está vacía.</p>}
      {state.kind === 'ready' && state.rows.length > 0 && (
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>Indicativo</th>
                <th>E-mail</th>
                <th>Notas</th>
                <th>Actualizado</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {state.rows.map((row) => (
                <tr key={row.callSign}>
                  <td className="nowrap">{row.callSign}</td>
                  <td>
                    {row.noMail && <span className="status status-failed">No enviar</span>}{' '}
                    {row.email ? (
                      <span className={row.emailInvalid ? 'struck' : undefined}>{row.email}</span>
                    ) : (
                      <span className="hint">{row.recipient.email ? `del log: ${row.recipient.email}` : 'sin e-mail'}</span>
                    )}
                  </td>
                  <td>{row.notes}</td>
                  <td className="nowrap">{row.updatedAt ? formatUtc(row.updatedAt) : ''}</td>
                  <td className="row-actions">
                    <button type="button" className="link" onClick={() => setEditing(row.callSign)}>
                      Editar
                    </button>
                    <button type="button" className="link" onClick={() => void remove(row.callSign)}>
                      Borrar
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}
