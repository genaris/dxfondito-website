import { describe, expect, it } from 'vitest'
import { actionName, detailLines, formatUtc } from './audit.ts'
import type { AuditEntry } from './audit.ts'

function entry(action: string, detail: Record<string, unknown> | null): AuditEntry {
  return { id: 1, user: { id: 1, callSign: 'LU1ADM' }, action, entityId: 2, detail, createdAt: '2026-10-01T12:34:56Z' }
}

describe('actionName', () => {
  it('names a known action', () => {
    expect(actionName('user.create')).toBe('Creó una cuenta')
  })

  it('gives the code of an unknown action', () => {
    expect(actionName('log.upload')).toBe('log.upload')
  })
})

describe('formatUtc', () => {
  it('gives the date and the time in UTC', () => {
    expect(formatUtc('2026-10-01T12:34:56Z')).toBe('2026-10-01 12:34 UTC')
  })
})

describe('detailLines', () => {
  it('explains a new account', () => {
    const lines = detailLines(
      entry('user.create', { callSign: 'LU2ABC', name: 'Ana', email: null, role: 'operator' }),
    )
    expect(lines).toEqual(['Cuenta LU2ABC', 'Nombre: Ana', 'Email: (vacío)', 'Rol: Operador'])
  })

  it('explains each change with the old and the new value', () => {
    const lines = detailLines(
      entry('user.update', {
        callSign: 'LU2ABC',
        changes: { role: ['operator', 'administrator'], active: [true, false] },
      }),
    )
    expect(lines).toEqual(['Cuenta LU2ABC', 'Rol: Operador → Administrador', 'Estado: activa → desactivada'])
  })

  it('tells about the first administrator', () => {
    const lines = detailLines(
      entry('user.create', { callSign: 'LU1ADM', name: 'A', email: null, role: 'administrator', firstAdministrator: true }),
    )
    expect(lines).toContain('Primer administrador, desde la página de migraciones')
  })

  it('accepts an entry without detail', () => {
    expect(detailLines(entry('log.upload', null))).toEqual([])
  })
})
