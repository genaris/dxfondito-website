import { describe, expect, it } from 'vitest'
import { href, matchPath, pathFromHash } from './router.ts'

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

describe('matchPath', () => {
  it('gives the parameters', () => {
    expect(matchPath('/actividad/:id', '/actividad/12')).toEqual({ id: '12' })
  })

  it('gives an empty object for a path without parameters', () => {
    expect(matchPath('/actividades', '/actividades')).toEqual({})
  })

  it('gives null for a different path', () => {
    expect(matchPath('/actividad/:id', '/actividades')).toBeNull()
    expect(matchPath('/actividad/:id', '/actividad/12/logs')).toBeNull()
    expect(matchPath('/actividad/:id', '/actividad/')).toBeNull()
  })

  it('decodes the values', () => {
    expect(matchPath('/participante/:call', '/participante/LU1ABC%2FP')).toEqual({ call: 'LU1ABC/P' })
  })
})
