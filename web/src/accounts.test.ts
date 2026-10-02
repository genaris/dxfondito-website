import { describe, expect, it } from 'vitest'
import { accountState } from './accounts.ts'
import type { Account } from './accounts.ts'

const account: Account = {
  id: 1,
  callSign: 'LU1ABC',
  name: 'Ana',
  role: 'operator',
  mustChangePassword: false,
  email: null,
  active: true,
  locked: false,
}

describe('accountState', () => {
  it('names an active account', () => {
    expect(accountState(account)).toBe('Activa')
  })

  it('gives the deactivation before the other states', () => {
    expect(accountState({ ...account, active: false, locked: true, mustChangePassword: true })).toBe('Desactivada')
  })

  it('names a locked account', () => {
    expect(accountState({ ...account, locked: true })).toBe('Bloqueada')
  })

  it('names an account with an initial password', () => {
    expect(accountState({ ...account, mustChangePassword: true })).toBe('Contraseña inicial')
  })
})
