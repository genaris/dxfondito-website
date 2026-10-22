import { useState } from 'react'
import type { FormEvent } from 'react'
import { signInError } from '../messages.ts'
import { navigate } from '../router.ts'
import { useSession } from '../useSession.ts'

/**
 * @param stay True to stay on the page after the sign-in, such as the live page. Else the home page comes.
 */
export function SignInPage({ stay = false }: { stay?: boolean }) {
  const { signIn } = useSession()
  const [callSign, setCallSign] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function submit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await signIn(callSign, password)
      if (!stay) navigate('/')
    } catch (failure) {
      setError(signInError(failure))
      setPassword('')
    } finally {
      setBusy(false)
    }
  }

  return (
    <form className="form" onSubmit={submit}>
      <h2>Ingresar</h2>
      <label>
        Indicativo
        <input
          value={callSign}
          onChange={(event) => setCallSign(event.target.value)}
          autoComplete="username"
          autoCapitalize="characters"
          required
          autoFocus
        />
      </label>
      <label>
        Contraseña
        <input
          type="password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          autoComplete="current-password"
          required
        />
      </label>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <button type="submit" disabled={busy}>
        {busy ? 'Ingresando…' : 'Ingresar'}
      </button>
    </form>
  )
}
