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
  endDate: string
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

/**
 * The season of an activity is the year of its start date (R-SEA-2a).
 */
export function seasonOf(startDate: string): number | null {
  return /^\d{4}-\d{2}-\d{2}$/.test(startDate) ? Number(startDate.slice(0, 4)) : null
}
