import { describe, expect, it } from 'vitest'
import { activityDates, referenceCode, seasonOf } from './activities.ts'

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
