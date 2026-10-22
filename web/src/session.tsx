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
    rememberUser(state.user)
  }, [])

  useEffect(() => {
    apiGet<SessionState>('/session')
      .then(apply)
      .catch((error: unknown) => {
        // Without a connection, the last user of this browser: the live page works without a connection
        // (FR-LIVE-10). The page has no token: it reads the session again later.
        if (error instanceof ApiError) apply({ user: null, token: null })
        else {
          setSessionToken(null)
          setUser(lastUser())
          setReady(true)
        }
      })
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
      refresh: async () => {
        apply(await apiGet<SessionState>('/session'))
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

const USER_KEY = 'dxf-session-user'

function rememberUser(user: User | null): void {
  try {
    if (user) localStorage.setItem(USER_KEY, JSON.stringify(user))
    else localStorage.removeItem(USER_KEY)
  } catch {
    // The storage can be absent, for example in a private window.
  }
}

function lastUser(): User | null {
  try {
    const text = localStorage.getItem(USER_KEY)
    return text === null ? null : (JSON.parse(text) as User)
  } catch {
    return null
  }
}
