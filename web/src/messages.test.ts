import { describe, expect, it } from 'vitest'
import { ApiError } from './api.ts'
import { newPasswordProblem, signInError } from './messages.ts'

describe('newPasswordProblem', () => {
  it('accepts a correct new password', () => {
    expect(newPasswordProblem('old password', 'new password', 'new password')).toBeNull()
  })

  it('refuses a short password', () => {
    expect(newPasswordProblem('old password', 'short', 'short')).toMatch(/8 o más/)
  })

  it('counts the characters, not the bytes', () => {
    expect(newPasswordProblem('old password', 'ñññññññ', 'ñññññññ')).toMatch(/8 o más/)
  })

  it('refuses a password of more than 72 bytes', () => {
    const long = 'ñ'.repeat(37)
    expect(newPasswordProblem('old password', long, long)).toMatch(/larga/)
  })

  it('refuses the current password', () => {
    expect(newPasswordProblem('same password', 'same password', 'same password')).toMatch(/distinta/)
  })

  it('refuses two different new passwords', () => {
    expect(newPasswordProblem('old password', 'new password', 'new passwordX')).toMatch(/no coinciden/)
  })
})

describe('signInError', () => {
  it('tells about a locked account', () => {
    expect(signInError(new ApiError(423, 'The account is locked'))).toMatch(/bloqueada/)
  })

  it('does not tell which value is incorrect', () => {
    expect(signInError(new ApiError(401, 'Incorrect call sign or password'))).toMatch(/indicativo o la contraseña/)
  })
})
