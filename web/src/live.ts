import { apiGet, apiSend, apiUrl } from './api.ts'
import type { Activity } from './activities.ts'
import { entityOf } from './entities.ts'
import type { Entity } from './entities.ts'
import type { Problem } from './logs.ts'
import { baseCallSign } from './ranking.ts'

// The live page: the contacts that the operators enter during an activity (FR-LIVE-1 to FR-LIVE-15, system design, section 5.3).

/** An active account. The station call sign has /A in a DPS activity. */
export interface LiveOperator {
  id: number
  callSign: string
  name: string
  station: string
}

/** The values that a user enters for a contact. */
export interface ContactInput {
  operatorId: number
  callSign: string
  name: string
  /** UTC, such as 2026-10-04T14:29:05Z. */
  qsoAt: string
  /** MHz, such as 7.130. */
  frequency: string
  mode: string
  rstSent: string
  rstRcvd: string
  notes: string
}

/** A contact as the API keeps it. */
export interface LiveContact {
  clientId: string
  version: number
  operatorId: number
  station: string
  callSign: string
  name: string | null
  qsoAt: string
  frequency: string
  band: string | null
  mode: string
  rstSent: string | null
  rstRcvd: string | null
  notes: string | null
  enteredBy: string
  updatedAt: string
}

/** A contact of an uploaded log of the activity, for the warning of a second contact. */
export interface UploadedContact {
  callSign: string
  qsoAt: string
  station: string
  band: string | null
}

export interface LiveChanges {
  contacts: LiveContact[]
  /** The identifiers of the deleted contacts. */
  deleted: string[]
  /** The `since` value of the next request. */
  cursor: string
  /** The time of the API, such as 2026-10-04T14:30:00.000Z: the page compares it with the clock of the device. */
  serverTime: string
}

export interface LiveState {
  activity: Activity
  operators: LiveOperator[]
  contacts: LiveContact[]
  uploaded: UploadedContact[]
  cursor: string
  serverTime: string
}

export interface Change {
  clientId: string
  op: 'save' | 'delete'
  baseVersion: number | null
  contact?: ContactInput
}

export interface ChangeResult {
  clientId: string | null
  status: 'ok' | 'conflict' | 'error'
  contact?: LiveContact
  /** For a conflict: the contact of the API, or null if a different browser deleted it. */
  server?: LiveContact | null
  error?: string
  problems?: Problem[]
}

export interface SyncAnswer {
  results: ChangeResult[]
  contacts?: LiveContact[]
  deleted?: string[]
  cursor: string
  serverTime: string
}

/** The help table: the names, and the references of each participant in the season (FR-LIVE-7). */
export interface Lookup {
  reference: string
  season: number
  names: Record<string, string>
  references: Record<string, string[]>
}

export function readLiveState(activityId: number): Promise<LiveState> {
  return apiGet<LiveState>(`/activities/${activityId}/live`)
}

export function readLiveChanges(activityId: number, since: string): Promise<LiveChanges> {
  return apiGet<LiveChanges>(`/activities/${activityId}/live`, { since })
}

export function syncLive(activityId: number, changes: Change[], since: string | null): Promise<SyncAnswer> {
  return apiSend<SyncAnswer>('POST', `/activities/${activityId}/live/sync`, { changes, since })
}

export function readLookup(activityId: number): Promise<Lookup> {
  return apiGet<Lookup>(`/activities/${activityId}/live/lookup`)
}

/** The ADIF file of all contacts of an operator in an activity, for QRZ.com and LdA (FR-LIVE-13). */
export function adifUrl(activityId: number, operatorId: number): string {
  return apiUrl(`/activities/${activityId}/adif`, { operatorId: String(operatorId) })
}

/** The modes of the program: only voice. */
export const MODES = ['SSB', 'FM'] as const

/** The bands of the program and their mode. `api/src/Live/Band.php` has the same limits. */
const BANDS: { lower: number; upper: number; band: string; mode: string | null }[] = [
  { lower: 1.8, upper: 2.0, band: '160m', mode: null },
  { lower: 3.5, upper: 4.0, band: '80m', mode: 'SSB' },
  { lower: 5.06, upper: 5.45, band: '60m', mode: null },
  { lower: 7.0, upper: 7.3, band: '40m', mode: 'SSB' },
  { lower: 10.1, upper: 10.15, band: '30m', mode: null },
  { lower: 14.0, upper: 14.35, band: '20m', mode: null },
  { lower: 18.068, upper: 18.168, band: '17m', mode: null },
  { lower: 21.0, upper: 21.45, band: '15m', mode: null },
  { lower: 24.89, upper: 24.99, band: '12m', mode: null },
  { lower: 28.0, upper: 29.7, band: '10m', mode: 'SSB' },
  { lower: 50.0, upper: 54.0, band: '6m', mode: null },
  { lower: 144.0, upper: 148.0, band: '2m', mode: 'FM' },
  { lower: 222.0, upper: 225.0, band: '1.25m', mode: null },
  { lower: 420.0, upper: 450.0, band: '70cm', mode: null },
]

/**
 * The frequency in MHz with three decimals or more, such as 7.130 for "7.13" or "7,13", or null for a wrong value.
 */
export function normalizeFrequency(text: string): string | null {
  const value = text.trim().replace(',', '.')
  if (!/^[0-9]{1,4}(\.[0-9]{1,6})?$/.test(value) || Number(value) <= 0) return null
  const [whole, decimals = ''] = value.split('.')
  return `${Number(whole)}.${decimals.replace(/0+$/, '').padEnd(3, '0')}`
}

/**
 * The band of a frequency, and the mode of the program on that band: SSB on 80 m, 40 m and 10 m, FM on 2 m.
 * The mode is null out of the bands of the program.
 */
export function bandOf(frequency: string): { band: string | null; mode: string | null } {
  const mhz = Number(frequency)
  const found = BANDS.find((band) => mhz >= band.lower && mhz <= band.upper)
  return { band: found?.band ?? null, mode: found?.mode ?? null }
}

/**
 * True for a complete call sign, such as LU1ABC, CX2AB or LU1ABC/P: the base has a digit and one to four letters
 * at the end. The page looks up a call sign only when it is complete.
 */
export function isCompleteCallSign(callSign: string): boolean {
  const base = baseCallSign(callSign)
  return base.length >= 4 && /^[A-Z0-9]{1,3}[0-9][A-Z]{1,4}$/.test(base)
}

/** The help for a call sign (FR-LIVE-7). */
export interface CallInfo {
  name: string | null
  entity: Entity
  /** The points of the participant in the season, with the contacts of this page. Null without the help table. */
  points: number | null
  /** True if this contact gives a new point. Null without the help table. */
  newPoint: boolean | null
  /** The other contacts of the participant in this activity, the newest first. */
  previous: { qsoAt: string; station: string; band: string | null }[]
}

/**
 * The help for a call sign, from the help table and the contacts of the page.
 *
 * @param contacts The contacts of the activity on this page, without the contact that the user edits.
 */
export function callInfo(
  callSign: string,
  lookup: Lookup | null,
  contacts: { callSign: string; qsoAt: string; station: string; band: string | null }[],
): CallInfo {
  const base = baseCallSign(callSign)
  const previous = contacts
    .filter((contact) => baseCallSign(contact.callSign) === base)
    .map(({ qsoAt, station, band }) => ({ qsoAt, station, band }))
    .sort((a, b) => b.qsoAt.localeCompare(a.qsoAt))
  if (lookup === null) return { name: null, entity: entityOf(callSign), points: null, newPoint: null, previous }

  const references = new Set(lookup.references[base] ?? [])
  // A contact of the page is a contact with the reference of the activity, also before the API has it.
  if (previous.length > 0) references.add(lookup.reference)
  const newPoint = !references.has(lookup.reference)
  return {
    name: lookup.names[base] ?? null,
    entity: entityOf(callSign),
    points: references.size,
    newPoint,
    previous,
  }
}

/**
 * The UTC time of the device as the API wants it, without milliseconds: 2026-10-04T14:29:05Z.
 */
export function utcSeconds(date: Date): string {
  return `${date.toISOString().slice(0, 19)}Z`
}

/**
 * The difference in milliseconds between the clock of the API and the clock of the device. The middle of the
 * request is the time of the answer of the API.
 */
export function clockOffset(serverTime: string, sentAt: number, receivedAt: number): number {
  return Date.parse(serverTime) - (sentAt + receivedAt) / 2
}

/** The warning for a device clock with a difference of more than a minute, or null. */
export function clockWarning(offset: number): string | null {
  const minutes = Math.round(Math.abs(offset) / 60000)
  if (Math.abs(offset) < 60000) return null
  const amount = minutes === 1 ? '1 minuto' : `${minutes} minutos`
  return `La hora de este equipo está ${amount} ${offset < 0 ? 'adelantada' : 'atrasada'}. Corríjala: los QSOs llevan la hora del equipo.`
}

/** An identifier for a new contact. */
export function newClientId(): string {
  return crypto.randomUUID()
}

/** The field names of the API in words, for the problems of a contact. */
const FIELDS: Record<string, string> = {
  CALL: 'el indicativo',
  QSO_DATE: 'la fecha y la hora',
  TIME_ON: 'la hora',
  FREQ: 'la frecuencia',
  MODE: 'el modo',
}

export function problemsText(problems: Problem[]): string {
  return problems
    .map((problem) => `${problem.problem === 'missing' ? 'falta' : 'revise'} ${FIELDS[problem.field] ?? problem.field}`)
    .join(', ')
}
