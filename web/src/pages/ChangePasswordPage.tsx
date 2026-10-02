import { useState } from 'react'
import type { FormEvent } from 'react'
import { MIN_PASSWORD_LENGTH, newPasswordProblem, passwordChangeError } from '../messages.ts'
import { navigate } from '../router.ts'
import { useSession } from '../useSession.ts'

/**
 * FR-AUT-3 and FR-AUT-4. With an initial password, the user sees only this page.
 */
export function ChangePasswordPage({ required }: { required: boolean }) {
  const { changePassword } = useSession()
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [repeated, setRepeated] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)
  const [busy, setBusy] = useState(false)

  async function submit(event: FormEvent) {
    event.preventDefault()
    const problem = newPasswordProblem(currentPassword, newPassword, repeated)
    if (problem) {
      setError(problem)
      return
    }
    setBusy(true)
    setError(null)
    try {
      await changePassword(currentPassword, newPassword)
      setDone(true)
      if (required) navigate('/')
    } catch (failure) {
      setError(passwordChangeError(failure))
    } finally {
      setBusy(false)
    }
  }

  if (done && !required) {
    return <p role="status">La contraseña se cambió.</p>
  }

  return (
    <form className="form" onSubmit={submit}>
      <h2>Cambiar la contraseña</h2>
      {required && <p>Su cuenta tiene una contraseña inicial. Elija una contraseña nueva para continuar.</p>}
      <label>
        Contraseña actual
        <input
          type="password"
          value={currentPassword}
          onChange={(event) => setCurrentPassword(event.target.value)}
          autoComplete="current-password"
          required
          autoFocus
        />
      </label>
      <label>
        Contraseña nueva
        <input
          type="password"
          value={newPassword}
          onChange={(event) => setNewPassword(event.target.value)}
          autoComplete="new-password"
          minLength={MIN_PASSWORD_LENGTH}
          required
        />
      </label>
      <label>
        Repetir la contraseña nueva
        <input
          type="password"
          value={repeated}
          onChange={(event) => setRepeated(event.target.value)}
          autoComplete="new-password"
          required
        />
      </label>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <button type="submit" disabled={busy}>
        {busy ? 'Guardando…' : 'Cambiar la contraseña'}
      </button>
    </form>
  )
}
