import { createContext, useContext } from 'react'

export interface User {
  id: number
  callSign: string
  name: string
  role: 'operator' | 'administrator'
  mustChangePassword: boolean
}

export interface SessionState {
  user: User | null
  token: string | null
}

export interface SessionValue {
  /** False until the first answer of the API. */
  ready: boolean
  user: User | null
  signIn: (callSign: string, password: string) => Promise<void>
  signOut: () => Promise<void>
  /** Reads the session again, for example after a change to the account of the user. */
  refresh: () => Promise<void>
  changePassword: (currentPassword: string, newPassword: string) => Promise<void>
}

export const SessionContext = createContext<SessionValue | null>(null)

export function useSession(): SessionValue {
  const value = useContext(SessionContext)
  if (value === null) throw new Error('useSession needs a SessionProvider')
  return value
}
