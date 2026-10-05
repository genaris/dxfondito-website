import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import {
  accountState,
  createAccount,
  listAccounts,
  ROLE_NAMES,
  setInitialPassword,
  updateAccount,
} from '../../accounts.ts'
import type { Account, ListedAccount, Role } from '../../accounts.ts'
import {
  accountCreateError,
  accountUpdateError,
  initialPasswordError,
  initialPasswordProblem,
  MIN_PASSWORD_LENGTH,
} from '../../messages.ts'
import { useSession } from '../../useSession.ts'

type ListState = { kind: 'loading' } | { kind: 'ready'; accounts: ListedAccount[] } | { kind: 'error' }

/**
 * The account administration (FR-USR-1 to FR-USR-9). The system does not delete accounts.
 */
export function UsersPage() {
  const { user, refresh } = useSession()
  const [list, setList] = useState<ListState>({ kind: 'loading' })
  const [editing, setEditing] = useState<number | 'new' | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  const load = useCallback(() => {
    listAccounts()
      .then((accounts) => setList({ kind: 'ready', accounts }))
      .catch(() => setList({ kind: 'error' }))
  }, [])

  useEffect(load, [load])

  function saved(account: Account, message: string) {
    setEditing(null)
    setNotice(message)
    load()
    // A change to the account of the user can change the role of the session.
    if (account.id === user?.id) void refresh()
  }

  function open(target: number | 'new') {
    setNotice(null)
    setEditing(target)
  }

  return (
    <>
      <h2>Cuentas</h2>
      {notice && <p role="status">{notice}</p>}
      {editing === 'new' ? (
        <NewAccountForm
          onDone={(account) => saved(account, `Se creó la cuenta ${account.callSign}.`)}
          onCancel={() => setEditing(null)}
        />
      ) : (
        <p>
          <button type="button" onClick={() => open('new')}>
            Nueva cuenta
          </button>
        </p>
      )}
      {list.kind === 'loading' && <p>Cargando…</p>}
      {list.kind === 'error' && <p className="error">No se pudo leer la lista de cuentas.</p>}
      {list.kind === 'ready' && (
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>Indicativo</th>
                <th>Nombre</th>
                <th>Rol</th>
                <th>Estado</th>
                <th className="number">QSOs</th>
                <th>
                  <span className="visually-hidden">Acciones</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {list.accounts.map((account) =>
                editing === account.id ? (
                  <tr key={account.id}>
                    <td colSpan={6}>
                      <EditAccount
                        account={account}
                        onDone={(changed, message) => saved(changed, message)}
                        onCancel={() => setEditing(null)}
                      />
                    </td>
                  </tr>
                ) : (
                  <tr key={account.id}>
                    <td>{account.callSign}</td>
                    <td>{account.name}</td>
                    <td>{ROLE_NAMES[account.role]}</td>
                    <td>{accountState(account)}</td>
                    <td className="number">{account.contactCount.toLocaleString('es-AR')}</td>
                    <td>
                      <button type="button" className="link" onClick={() => open(account.id)}>
                        Editar
                      </button>
                    </td>
                  </tr>
                ),
              )}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}

function RoleSelect({ value, onChange }: { value: Role; onChange: (role: Role) => void }) {
  return (
    <label>
      Rol
      <select value={value} onChange={(event) => onChange(event.target.value as Role)}>
        <option value="operator">{ROLE_NAMES.operator}</option>
        <option value="administrator">{ROLE_NAMES.administrator}</option>
      </select>
    </label>
  )
}

function NewAccountForm({ onDone, onCancel }: { onDone: (account: Account) => void; onCancel: () => void }) {
  const [callSign, setCallSign] = useState('')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [role, setRole] = useState<Role>('operator')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function submit(event: FormEvent) {
    event.preventDefault()
    const problem = initialPasswordProblem(password)
    if (problem) {
      setError(problem)
      return
    }
    setBusy(true)
    setError(null)
    try {
      onDone(await createAccount({ callSign, name, email, role, password }))
    } catch (failure) {
      setError(accountCreateError(failure))
      setBusy(false)
    }
  }

  return (
    <form className="form" onSubmit={submit}>
      <h3>Nueva cuenta</h3>
      <label>
        Indicativo
        <input
          value={callSign}
          onChange={(event) => setCallSign(event.target.value)}
          pattern="[A-Za-z0-9/ ]{3,20}"
          autoCapitalize="characters"
          required
          autoFocus
        />
      </label>
      <label>
        Nombre
        <input value={name} onChange={(event) => setName(event.target.value)} maxLength={100} required />
      </label>
      <label>
        Email (opcional)
        <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} maxLength={254} />
      </label>
      <RoleSelect value={role} onChange={setRole} />
      <label>
        Contraseña inicial
        <input
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          minLength={MIN_PASSWORD_LENGTH}
          autoComplete="off"
          required
        />
      </label>
      <p className="hint">El usuario debe cambiar la contraseña inicial cuando ingresa por primera vez.</p>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <div className="actions">
        <button type="submit" disabled={busy}>
          {busy ? 'Creando…' : 'Crear la cuenta'}
        </button>
        <button type="button" onClick={onCancel}>
          Cancelar
        </button>
      </div>
    </form>
  )
}

function EditAccount({
  account,
  onDone,
  onCancel,
}: {
  account: Account
  onDone: (account: Account, message: string) => void
  onCancel: () => void
}) {
  const [name, setName] = useState(account.name)
  const [email, setEmail] = useState(account.email ?? '')
  const [role, setRole] = useState<Role>(account.role)
  const [active, setActive] = useState(account.active)
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function save(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const changed = await updateAccount(account.id, { name, email, role, active })
      onDone(changed, `Se guardaron los cambios de ${account.callSign}.`)
    } catch (failure) {
      setError(accountUpdateError(failure))
      setBusy(false)
    }
  }

  async function resetPassword(event: FormEvent) {
    event.preventDefault()
    const problem = initialPasswordProblem(password)
    if (problem) {
      setError(problem)
      return
    }
    setBusy(true)
    setError(null)
    try {
      const changed = await setInitialPassword(account.id, password)
      onDone(changed, `${account.callSign} tiene una contraseña inicial nueva.`)
    } catch (failure) {
      setError(initialPasswordError(failure))
      setBusy(false)
    }
  }

  return (
    <div className="edit-account">
      <form className="form" onSubmit={save}>
        <h3>{account.callSign}</h3>
        <label>
          Nombre
          <input value={name} onChange={(event) => setName(event.target.value)} maxLength={100} required />
        </label>
        <label>
          Email (opcional)
          <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} maxLength={254} />
        </label>
        <RoleSelect value={role} onChange={setRole} />
        <label className="checkbox">
          <input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} />
          Cuenta activa
        </label>
        {!active && <p className="hint">Una cuenta desactivada no puede ingresar. Sus logs quedan en el sistema.</p>}
        <div className="actions">
          <button type="submit" disabled={busy}>
            Guardar
          </button>
          <button type="button" onClick={onCancel}>
            Cancelar
          </button>
        </div>
      </form>
      <form className="form" onSubmit={resetPassword}>
        <h4>Contraseña inicial nueva</h4>
        <p className="hint">
          El usuario deberá cambiarla cuando ingrese. Si la cuenta está bloqueada, también la desbloquea.
        </p>
        <label>
          Contraseña inicial
          <input
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            minLength={MIN_PASSWORD_LENGTH}
            autoComplete="off"
            required
          />
        </label>
        <div className="actions">
          <button type="submit" disabled={busy}>
            Poner la contraseña
          </button>
        </div>
      </form>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
    </div>
  )
}
