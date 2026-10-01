import { describe, expect, it } from 'vitest'
import { apiUrl } from './api.ts'

describe('apiUrl', () => {
  it('puts the path in the r parameter', () => {
    expect(apiUrl('/seasons/2026/ranking')).toBe('api/index.php?r=/seasons/2026/ranking')
  })

  it('adds the other parameters after the path', () => {
    expect(apiUrl('/seasons/2026/ranking', { search: 'LU1' })).toBe(
      'api/index.php?r=/seasons/2026/ranking&search=LU1',
    )
  })

  it('gives a relative address', () => {
    expect(apiUrl('/health').startsWith('/')).toBe(false)
  })

  it('keeps the & character in the path', () => {
    expect(apiUrl('/participants/A&B')).toBe('api/index.php?r=/participants/A%26B')
  })
})
