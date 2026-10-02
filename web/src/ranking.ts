import { apiGet } from './api.ts'

export interface RankingRow {
  position: number
  callSign: string
  points: number
  /** The codes of the references with a contact, such as ['DPS-01', 'EFE-03'] (D-25). */
  references: string[]
  /** The reached certificate levels, such as [5, 10]. */
  levels: number[]
}

export interface Ranking {
  season: number
  levels: number[]
  rows: RankingRow[]
}

export interface FirstContact {
  referenceId: number
  reference: string
  referenceName: string
  activityId: number
  /** The call sign as the log gives it, such as LU1ABC/P. */
  callSign: string
  qsoAt: string
  frequency: string | null
  band: string | null
  mode: string
  operator: string
  /** True if the operator of the first contact has a QSL card template for its activity (FR-QSL-11). */
  qsl: boolean
}

export interface ParticipantSeason {
  season: number
  points: number
  /** Only for the current season (FR-PUB-8b). Null after the highest level. */
  pointsToNextLevel: number | null
  references: FirstContact[]
  certificates: { points: number; date: string }[]
}

export interface Participant {
  callSign: string
  current: number
  levels: number[]
  seasons: ParticipantSeason[]
}

export function readRanking(season: number): Promise<Ranking> {
  return apiGet<Ranking>(`/seasons/${season}/ranking`)
}

export function readParticipant(callSign: string): Promise<Participant> {
  return apiGet<Participant>(`/participants/${callSign}`)
}

/**
 * The text of a search, as the API stores call signs: uppercase letters, no spaces.
 */
export function normalizeSearch(text: string): string {
  return text.toUpperCase().replace(/\s+/g, '')
}

/**
 * The lines with the search text in the call sign (FR-PUB-6). The lines keep their positions.
 */
export function searchRanking(rows: RankingRow[], text: string): RankingRow[] {
  const search = normalizeSearch(text)
  return search === '' ? rows : rows.filter((row) => row.callSign.includes(search))
}

/**
 * The base call sign: the longest part between the / characters (system design, section 4.1).
 */
export function baseCallSign(callSign: string): string {
  return normalizeSearch(callSign)
    .split('/')
    .reduce((longest, part) => (part.length > longest.length ? part : longest), '')
}
