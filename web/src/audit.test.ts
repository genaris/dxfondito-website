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
    expect(actionName('template.upload')).toBe('template.upload')
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

describe('detailLines of references and activities', () => {
  it('explains a new activity', () => {
    const lines = detailLines(
      entry('activity.create', { label: 'DPS-01 (2026-05-10)', name: 'Hospital', endDate: '2026-05-11', description: null }),
    )
    expect(lines).toEqual(['Actividad DPS-01 (2026-05-10)', 'Nombre: Hospital', 'Fin: 2026-05-11', 'Descripción: (vacío)'])
  })

  it('explains a change of a reference', () => {
    const lines = detailLines(
      entry('reference.update', { code: 'DPS-01', changes: { code: ['DPS-01', 'DPS-02'] } }),
    )
    expect(lines).toEqual(['Referencia DPS-01', 'Código: DPS-01 → DPS-02'])
  })
})

describe('detailLines of logs', () => {
  it('explains an upload', () => {
    const lines = detailLines(
      entry('log.upload', { label: 'DPS-01 (2026-05-10)', fileName: 'a.adi', operator: 'LU1ABC', contacts: 12 }),
    )
    expect(lines).toEqual(['Actividad DPS-01 (2026-05-10)', 'Archivo: a.adi', 'Operador: LU1ABC', 'Contactos: 12'])
  })
})
