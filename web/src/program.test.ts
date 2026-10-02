import { describe, expect, it } from 'vitest'
import { levelName, longDate, nextActivity, todayUtc } from './program.ts'

describe('levelName', () => {
  it('names the three levels', () => {
    expect([5, 10, 15].map(levelName)).toEqual(['Bronce', 'Plata', 'Oro'])
  })

  it('gives the points for a different level', () => {
    expect(levelName(20)).toBe('20 puntos')
  })
})

describe('nextActivity', () => {
  const activities = [
    { id: 1, startDate: '2026-09-27', endDate: '2026-09-27' },
    { id: 3, startDate: '2026-10-11', endDate: '2026-10-11' },
    { id: 2, startDate: '2026-10-04', endDate: '2026-10-04' },
  ]

  it('gives the earliest activity that has not ended', () => {
    expect(nextActivity(activities, '2026-10-02')?.id).toBe(2)
  })

  it('gives an activity of today', () => {
    expect(nextActivity(activities, '2026-10-04')?.id).toBe(2)
  })

  it('gives an activity in progress', () => {
    const long = [{ id: 4, startDate: '2026-10-01', endDate: '2026-10-05' }]
    expect(nextActivity(long, '2026-10-03')?.id).toBe(4)
  })

  it('gives null after the last activity', () => {
    expect(nextActivity(activities, '2026-12-01')).toBeNull()
  })
})

describe('dates', () => {
  it('gives today in UTC', () => {
    expect(todayUtc(new Date('2026-10-02T23:30:00-03:00'))).toBe('2026-10-03')
  })

  it('gives a date in words', () => {
    expect(longDate('2026-10-04')).toBe('domingo 4 de octubre')
  })
})
