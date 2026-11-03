import { describe, expect, it } from 'vitest'
import { bandOf, callInfo, clockOffset, clockWarning, isCompleteCallSign, normalizeFrequency, problemsText } from './live.ts'
import type { Lookup } from './live.ts'

describe('normalizeFrequency', () => {
  it('gives three decimals or more', () => {
    expect(normalizeFrequency('7.13')).toBe('7.130')
    expect(normalizeFrequency('7,130')).toBe('7.130')
    expect(normalizeFrequency('145.75')).toBe('145.750')
    expect(normalizeFrequency('7.1305')).toBe('7.1305')
    expect(normalizeFrequency('7')).toBe('7.000')
  })

  it('refuses a wrong value', () => {
    expect(normalizeFrequency('')).toBeNull()
    expect(normalizeFrequency('siete')).toBeNull()
    expect(normalizeFrequency('0')).toBeNull()
  })
})

describe('bandOf', () => {
  it('gives the band and the mode of the program', () => {
    expect(bandOf('3.650')).toEqual({ band: '80m', mode: 'SSB' })
    expect(bandOf('7.130')).toEqual({ band: '40m', mode: 'SSB' })
    expect(bandOf('28.400')).toEqual({ band: '10m', mode: 'SSB' })
    expect(bandOf('145.750')).toEqual({ band: '2m', mode: 'FM' })
  })

  it('gives no mode out of the bands of the program', () => {
    expect(bandOf('14.200')).toEqual({ band: '20m', mode: null })
    expect(bandOf('7.400')).toEqual({ band: null, mode: null })
  })
})

describe('isCompleteCallSign', () => {
  it('accepts a call sign with the letters after the digit', () => {
    expect(isCompleteCallSign('LU1ABC')).toBe(true)
    expect(isCompleteCallSign('cx2ab')).toBe(true)
    expect(isCompleteCallSign('LU1ABC/P')).toBe(true)
    expect(isCompleteCallSign('LU1')).toBe(false)
    expect(isCompleteCallSign('LU')).toBe(false)
  })
})

describe('callInfo', () => {
  const lookup: Lookup = {
    reference: 'DPS-05',
    season: 2026,
    names: { LU1ABC: 'Juana Pérez' },
    references: { LU1ABC: ['DPS-01', 'EFE-02'], LU5DX: ['DPS-05'] },
  }

  it('gives the name, the entity and a new point', () => {
    const info = callInfo('LU1ABC', lookup, [])

    expect(info.name).toBe('Juana Pérez')
    expect(info.entity.name).toBe('Ciudad de Buenos Aires')
    expect(info.points).toBe(2)
    expect(info.newPoint).toBe(true)
  })

  it('gives no new point for a participant with the reference', () => {
    expect(callInfo('LU5DX', lookup, []).newPoint).toBe(false)
  })

  it('counts the contacts of the page before the API has them', () => {
    const info = callInfo('LU1ABC', lookup, [
      { callSign: 'LU1ABC/M', qsoAt: '2026-10-04T14:00:00Z', station: 'LU3EBQ/A', band: '2m' },
      { callSign: 'CX2AB', qsoAt: '2026-10-04T14:10:00Z', station: 'LU3EBQ/A', band: '2m' },
    ])

    expect(info.points).toBe(3)
    expect(info.newPoint).toBe(false)
    expect(info.previous).toEqual([{ qsoAt: '2026-10-04T14:00:00Z', station: 'LU3EBQ/A', band: '2m' }])
  })

  it('gives no points without the help table', () => {
    expect(callInfo('LU1ABC', null, [])).toMatchObject({ name: null, points: null, newPoint: null })
  })
})

describe('clockWarning', () => {
  it('warns for a difference of more than a minute', () => {
    expect(clockWarning(30000)).toBeNull()
    expect(clockWarning(-180000)).toBe(
      'La hora de este equipo está 3 minutos adelantada. Corríjala: los QSOs llevan la hora del equipo.',
    )
    expect(clockWarning(65000)).toContain('1 minuto atrasada')
  })

  it('uses the middle of the request', () => {
    expect(clockOffset('2026-10-04T14:30:00.000Z', Date.parse('2026-10-04T14:31:00Z'), Date.parse('2026-10-04T14:31:02Z'))).toBe(-61000)
  })
})

describe('problemsText', () => {
  it('gives the problems in words', () => {
    expect(
      problemsText([
        { field: 'CALL', problem: 'missing' },
        { field: 'FREQ', problem: 'invalid' },
      ]),
    ).toBe('falta el indicativo, revise la frecuencia')
  })
})
