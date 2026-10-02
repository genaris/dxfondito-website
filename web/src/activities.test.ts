import { describe, expect, it } from 'vitest'
import { activityDates, activitySchedule, argentinaHours, referenceCode, seasonOf } from './activities.ts'

describe('referenceCode', () => {
  it('gives two or more digits', () => {
    expect(referenceCode('DPS', 1)).toBe('DPS-01')
    expect(referenceCode('EFE', 12)).toBe('EFE-12')
    expect(referenceCode('DPS', 123)).toBe('DPS-123')
  })
})

describe('activityDates', () => {
  it('gives one date for an activity of one day', () => {
    expect(activityDates({ startDate: '2026-05-10', endDate: '2026-05-10' })).toBe('2026-05-10')
  })

  it('gives the start and the end', () => {
    expect(activityDates({ startDate: '2026-05-10', endDate: '2026-05-12' })).toBe('2026-05-10 al 2026-05-12')
  })
})

describe('seasonOf', () => {
  it('gives the year of the start date', () => {
    expect(seasonOf('2026-12-31')).toBe(2026)
  })

  it('gives null without a complete date', () => {
    expect(seasonOf('')).toBeNull()
    expect(seasonOf('2026-1')).toBeNull()
  })
})

describe('activitySchedule', () => {
  it('gives the date and the hours of one day', () => {
    const activity = { startDate: '2026-10-04', endDate: '2026-10-04', startTime: '13:00', endTime: '18:00' }
    expect(activitySchedule(activity)).toBe('2026-10-04 · 13:00 a 18:00 UTC')
    expect(argentinaHours(activity)).toBe('10:00 a 15:00')
  })

  it('gives the start and the end of more than one day', () => {
    const activity = { startDate: '2026-10-02', endDate: '2026-10-03', startTime: '20:00', endTime: '02:00' }
    expect(activitySchedule(activity)).toBe('2026-10-02 20:00 al 2026-10-03 02:00 UTC')
    expect(argentinaHours(activity)).toBe('17:00 a 23:00')
  })

  it('tells that the hours of an old activity are not known', () => {
    const activity = { startDate: '2026-10-04', endDate: '2026-10-04', startTime: null, endTime: null }
    expect(activitySchedule(activity)).toBe('2026-10-04 · horario a confirmar')
    expect(argentinaHours(activity)).toBeNull()
  })
})
