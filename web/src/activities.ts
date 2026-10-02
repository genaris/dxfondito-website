import { apiGet, apiSend } from './api.ts'

export interface Series {
  id: number
  code: string
  name: string
  /** The proposal for a new reference (FR-REF-3). */
  nextNumber: number
}

export interface Reference {
  id: number
  /** Such as DPS-01. */
  code: string
  seriesId: number
  series: string
  number: number
  name: string
  description: string | null
}

export interface Activity {
  id: number
  /** Such as "DPS-01 (2026-05-10)". */
  label: string
  reference: Reference
  season: number
  /** YYYY-MM-DD, UTC. */
  startDate: string
  endDate: string
  /** HH:MM, UTC (FR-ACT-3b). Null for an activity without hours: an activity before the change of 2026-10-02. */
  startTime: string | null
  endTime: string | null
  description: string | null
}

export interface Seasons {
  current: number
  /** The newest first. */
  seasons: number[]
}

export interface ReferenceOverview {
  series: Series[]
  references: Reference[]
}

export interface ReferenceData {
  seriesId: number
  number: number
  name: string
  description: string
}

export interface ActivityData {
  referenceId: number
  startDate: string
  startTime: string
  endDate: string
  endTime: string
  description: string
}

export function readSeasons(): Promise<Seasons> {
  return apiGet<Seasons>('/seasons')
}

export function readActivities(season: number): Promise<Activity[]> {
  return apiGet<Activity[]>(`/seasons/${season}/activities`)
}

/** A contact of an activity (FR-PUB-15). */
export interface ActivityContact {
  id: number
  /** The call sign as the log gives it, such as LU1ABC/P. */
  callSign: string
  baseCallSign: string
  operator: string
  qsoAt: string
  frequency: string | null
  band: string | null
  mode: string
  /** True if the operator of the contact has a QSL card template for this activity (FR-QSL-11). */
  qsl: boolean
}

/** An activity with the call signs of its operators (FR-ACT-2a) and all its contacts (FR-PUB-14, FR-PUB-15). */
export interface ActivityDetail extends Activity {
  operators: string[]
  /** The different base call signs of the contacts (FR-PUB-14a). */
  participantCount: number
  /** All contacts, in the order of time. A participant can have more than one contact. */
  contacts: ActivityContact[]
}

export function readActivity(id: number): Promise<ActivityDetail> {
  return apiGet<ActivityDetail>(`/activities/${id}`)
}

export function readReferences(): Promise<ReferenceOverview> {
  return apiGet<ReferenceOverview>('/references')
}

export function createReference(data: ReferenceData): Promise<Reference> {
  return apiSend<Reference>('POST', '/references', data)
}

export function updateReference(id: number, data: ReferenceData): Promise<Reference> {
  return apiSend<Reference>('PUT', `/references/${id}`, data)
}

export function deleteReference(id: number): Promise<unknown> {
  return apiSend('DELETE', `/references/${id}`)
}

export function createActivity(data: ActivityData): Promise<Activity> {
  return apiSend<Activity>('POST', '/activities', data)
}

export function updateActivity(id: number, data: ActivityData): Promise<Activity> {
  return apiSend<Activity>('PUT', `/activities/${id}`, data)
}

export function deleteActivity(id: number): Promise<unknown> {
  return apiSend('DELETE', `/activities/${id}`)
}

/**
 * The code with two or more digits (FR-REF-5).
 */
export function referenceCode(seriesCode: string, number: number): string {
  return `${seriesCode}-${String(number).padStart(2, '0')}`
}

/**
 * The dates of an activity in UTC: one date, or the start and the end.
 */
export function activityDates(activity: Pick<Activity, 'startDate' | 'endDate'>): string {
  return activity.startDate === activity.endDate
    ? activity.startDate
    : `${activity.startDate} al ${activity.endDate}`
}

type Schedule = Pick<Activity, 'startDate' | 'endDate' | 'startTime' | 'endTime'>

/** The hours of an activity, such as "13:00 a 18:00 UTC", or null for an activity without hours. */
export function activityHours(activity: Schedule): string | null {
  if (!activity.startTime || !activity.endTime) return null
  return `${activity.startTime} a ${activity.endTime} UTC`
}

/** Argentina has UTC−3 all year. */
const ARGENTINA_OFFSET = -3

/** The hours of an activity in the time of Argentina, such as "10:00 a 15:00", or null without hours. */
export function argentinaHours(activity: Schedule): string | null {
  if (!activity.startTime || !activity.endTime) return null
  const local = (time: string) => {
    const hour = (Number(time.slice(0, 2)) + ARGENTINA_OFFSET + 24) % 24
    return `${String(hour).padStart(2, '0')}${time.slice(2)}`
  }
  return `${local(activity.startTime)} a ${local(activity.endTime)}`
}

/**
 * The dates and the hours of an activity in UTC, such as "2026-10-04 · 13:00 a 18:00 UTC",
 * or "2026-10-02 20:00 al 2026-10-03 02:00 UTC" for an activity of more than one day.
 */
export function activitySchedule(activity: Schedule): string {
  const hours = activityHours(activity)
  if (!hours) return `${activityDates(activity)} · horario a confirmar`
  if (activity.startDate === activity.endDate) return `${activity.startDate} · ${hours}`
  return `${activity.startDate} ${activity.startTime} al ${activity.endDate} ${activity.endTime} UTC`
}

/** The start and the end of an activity as YYYY-MM-DDTHH:MM in UTC. Without hours, the whole days. */
export function activityPeriod(activity: Schedule): { start: string; end: string } {
  return {
    start: `${activity.startDate}T${activity.startTime ?? '00:00'}`,
    end: `${activity.endDate}T${activity.endTime ?? '23:59'}`,
  }
}

/**
 * The season of an activity is the year of its start date (R-SEA-2a).
 */
export function seasonOf(startDate: string): number | null {
  return /^\d{4}-\d{2}-\d{2}$/.test(startDate) ? Number(startDate.slice(0, 4)) : null
}
