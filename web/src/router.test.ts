import { describe, expect, it } from 'vitest'
import { href, pathFromHash } from './router.ts'

describe('pathFromHash', () => {
  it('gives the path after the # character', () => {
    expect(pathFromHash('#/participante/LU1ABC')).toBe('/participante/LU1ABC')
  })

  it('gives the root path for an empty hash', () => {
    expect(pathFromHash('')).toBe('/')
    expect(pathFromHash('#')).toBe('/')
  })

  it('adds the first / character if it is absent', () => {
    expect(pathFromHash('#ingresar')).toBe('/ingresar')
  })
})

describe('href', () => {
  it('gives a hash address', () => {
    expect(href('/ingresar')).toBe('#/ingresar')
  })
})
