import { describe, expect, it } from 'vitest'
import { activityState, CALENDAR, levelName, longDate, nextActivity, nowUtc, todayUtc } from './program.ts'

describe('levelName', () => {
  it('names the three levels', () => {
    expect([5, 10, 15].map(levelName)).toEqual(['Bronce', 'Plata', 'Oro'])
  })

  it('gives the points for a different level', () => {
    expect(levelName(20)).toBe('20 puntos')
  })
})

describe('nextActivity', () => {
  const day = (id: number, date: string, startTime: string | null = '13:00', endTime: string | null = '18:00') => ({
    id,
    startDate: date,
    endDate: date,
    startTime,
    endTime,
  })
  const activities = [day(1, '2026-09-27'), day(3, '2026-10-11'), day(2, '2026-10-04')]

  it('gives the earliest activity that has not ended', () => {
    expect(nextActivity(activities, '2026-10-02T12:00')?.id).toBe(2)
  })

  it('gives an activity of today before its end', () => {
    expect(nextActivity(activities, '2026-10-04T17:59')?.id).toBe(2)
  })

  it('gives the following activity after the end of the hours of today', () => {
    expect(nextActivity(activities, '2026-10-04T18:01')?.id).toBe(3)
  })

  it('keeps an activity without hours until the end of its day', () => {
    expect(nextActivity([day(4, '2026-10-04', null, null)], '2026-10-04T23:00')?.id).toBe(4)
  })

  it('gives an activity in progress of more than one day', () => {
    const long = [{ id: 5, startDate: '2026-10-02', endDate: '2026-10-03', startTime: '20:00', endTime: '02:00' }]
    expect(nextActivity(long, '2026-10-03T01:00')?.id).toBe(5)
  })

  it('gives null after the last activity', () => {
    expect(nextActivity(activities, '2026-12-01T00:00')).toBeNull()
  })
})

describe('activityState', () => {
  const activity = { startDate: '2026-10-04', endDate: '2026-10-04', startTime: '13:00', endTime: '18:00' }

  it('tells if the activity is on the air, later today or on a later day', () => {
    expect(activityState(activity, '2026-10-04T14:00')).toBe('live')
    expect(activityState(activity, '2026-10-04T10:00')).toBe('today')
    expect(activityState(activity, '2026-10-02T14:00')).toBe('later')
  })
})

describe('dates', () => {
  it('gives now in UTC', () => {
    expect(nowUtc(new Date('2026-10-02T22:30:00-03:00'))).toBe('2026-10-03T01:30')
  })

  it('gives today in UTC', () => {
    expect(todayUtc(new Date('2026-10-02T23:30:00-03:00'))).toBe('2026-10-03')
  })

  it('gives a date in words', () => {
    expect(longDate('2026-10-04')).toBe('domingo 4 de octubre')
  })
})

describe('CALENDAR', () => {
  it('has the events of the season in the order of the dates', () => {
    const dates = CALENDAR.events.map((event) => event.date)
    expect(dates).toEqual([...dates].sort())
    expect(dates.every((date) => date.startsWith(`${CALENDAR.season}-`))).toBe(true)
  })
})
