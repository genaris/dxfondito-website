import { apiGet, apiSend } from './api.ts'
import type { User } from './useSession.ts'

export type Role = User['role']

export interface Account extends User {
  email: string | null
  active: boolean
  locked: boolean
}

/**
 * An account in the account list, with the number of contacts in its logs (FR-USR-9).
 */
export interface ListedAccount extends Account {
  contactCount: number
}

export interface AccountChanges {
  name: string
  email: string
  role: Role
  active: boolean
}

export const ROLE_NAMES: Record<Role, string> = {
  operator: 'Operador',
  administrator: 'Administrador',
}

export function listAccounts(): Promise<ListedAccount[]> {
  return apiGet<ListedAccount[]>('/users')
}

export function createAccount(data: {
  callSign: string
  name: string
  email: string
  role: Role
  password: string
}): Promise<Account> {
  return apiSend<Account>('POST', '/users', data)
}

export function updateAccount(id: number, changes: AccountChanges): Promise<Account> {
  return apiSend<Account>('PUT', `/users/${id}`, changes)
}

export function setInitialPassword(id: number, password: string): Promise<Account> {
  return apiSend<Account>('PUT', `/users/${id}/password`, { password })
}

/**
 * The state of an account in words, for the account list.
 */
export function accountState(account: Account): string {
  if (!account.active) return 'Desactivada'
  if (account.locked) return 'Bloqueada'
  if (account.mustChangePassword) return 'Contraseña inicial'
  return 'Activa'
}
