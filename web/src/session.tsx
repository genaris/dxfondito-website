import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { ApiError, apiGet, apiSend, setSessionToken } from './api.ts'
import { SessionContext } from './useSession.ts'
import type { SessionState, SessionValue, User } from './useSession.ts'

export function SessionProvider({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false)
  const [user, setUser] = useState<User | null>(null)

  const apply = useCallback((state: SessionState) => {
    setSessionToken(state.token)
    setUser(state.user)
    setReady(true)
  }, [])

  useEffect(() => {
    apiGet<SessionState>('/session')
      .then(apply)
      .catch(() => apply({ user: null, token: null }))
  }, [apply])

  const value = useMemo<SessionValue>(
    () => ({
      ready,
      user,
      signIn: async (callSign, password) => {
        apply(await apiSend<SessionState>('POST', '/session', { callSign, password }))
      },
      signOut: async () => {
        apply(await apiSend<SessionState>('DELETE', '/session'))
      },
      changePassword: async (currentPassword, newPassword) => {
        try {
          apply(await apiSend<SessionState>('PUT', '/session/password', { currentPassword, newPassword }))
        } catch (error) {
          // The session ended, for example after two hours without activity.
          if (error instanceof ApiError && error.status === 401) apply({ user: null, token: null })
          throw error
        }
      },
    }),
    [ready, user, apply],
  )

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>
}
