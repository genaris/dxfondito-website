import { describe, expect, it } from 'vitest'
import { frequencyText, invalidRecordText, isAdifFileName, warningTexts } from './logs.ts'

describe('isAdifFileName', () => {
  it('accepts .adi and .adif in all cases', () => {
    expect(isAdifFileName('log.adi')).toBe(true)
    expect(isAdifFileName('LOG.ADIF')).toBe(true)
    expect(isAdifFileName('log.txt')).toBe(false)
    expect(isAdifFileName('adi')).toBe(false)
  })
})

describe('invalidRecordText', () => {
  it('gives the record, the call sign and each problem', () => {
    expect(
      invalidRecordText({
        record: 3,
        callSign: 'PY1A',
        problems: [
          { field: 'QSO_DATE', problem: 'invalid' },
          { field: 'MODE', problem: 'missing' },
        ],
      }),
    ).toBe('Registro 3 (PY1A): QSO_DATE con un valor incorrecto, falta MODE.')
  })

  it('accepts a record without call sign', () => {
    expect(invalidRecordText({ record: 1, callSign: null, problems: [{ field: 'CALL', problem: 'missing' }] })).toBe(
      'Registro 1: falta CALL.',
    )
  })
})

describe('warningTexts', () => {
  it('gives each date warning and one line for each station', () => {
    const texts = warningTexts({
      operator: { id: 1, callSign: 'LU1ABC' },
      warnings: [
        { record: 1, callSign: 'LU2ABC', kind: 'date', value: '2026-05-09' },
        { record: 1, callSign: 'LU2ABC', kind: 'station', value: 'LU9XYZ' },
        { record: 2, callSign: 'CX1A', kind: 'station', value: 'LU9XYZ' },
      ],
    })
    expect(texts).toEqual([
      'Registro 1 (LU2ABC): la fecha 2026-05-09 está fuera de la actividad.',
      'STATION_CALLSIGN es LU9XYZ en 2 registros. No es el indicativo del operador LU1ABC.',
    ])
  })
})

describe('frequencyText', () => {
  it('gives the frequency, or the band without a frequency', () => {
    expect(frequencyText({ frequency: '7.074', band: '40m' })).toBe('7.074 MHz')
    expect(frequencyText({ frequency: null, band: '40m' })).toBe('40m')
  })
})
