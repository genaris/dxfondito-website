import { apiGet, apiSend, apiUpload, apiUrl } from './api.ts'

export interface Log {
  id: number
  activityId: number
  operator: { id: number; callSign: string }
  uploadedBy: { id: number; callSign: string }
  fileName: string
  contactCount: number
  /** UTC, such as 2026-10-01T12:00:00Z. */
  uploadedAt: string
  /** True for the operator of the log and the administrators. */
  canManage: boolean
}

export interface Contact {
  callSign: string
  name: string | null
  qsoAt: string
  frequency: string | null
  band: string | null
  mode: string
  rstSent: string | null
  rstRcvd: string | null
}

export interface Problem {
  field: string
  problem: 'missing' | 'invalid'
}

export interface Warning {
  record: number
  callSign: string
  kind: 'date' | 'station'
  value: string
}

export interface Summary {
  fileName: string
  operator: { id: number; callSign: string }
  validCount: number
  invalid: { record: number; callSign: string | null; problems: Problem[] }[]
  warnings: Warning[]
}

export const MAX_FILE_BYTES = 5 * 1024 * 1024

export function readLogs(activityId: number): Promise<Log[]> {
  return apiGet<Log[]>(`/activities/${activityId}/logs`)
}

export function readLog(id: number): Promise<Log & { contacts: Contact[] }> {
  return apiGet<Log & { contacts: Contact[] }>(`/logs/${id}`)
}

export function deleteLog(id: number): Promise<unknown> {
  return apiSend('DELETE', `/logs/${id}`)
}

export function logFileUrl(id: number): string {
  return apiUrl(`/logs/${id}/file`)
}

/**
 * The first step of the upload: the API reads the file and saves nothing (FR-LOG-10).
 */
export function previewLog(activityId: number, file: File, operatorId: number | null): Promise<Summary> {
  return apiUpload<Summary>(`/activities/${activityId}/logs`, uploadForm('preview', file, operatorId))
}

/**
 * The second step: the browser sends the same file again, and the API saves it (FR-LOG-15).
 */
export function saveLog(activityId: number, file: File, operatorId: number | null): Promise<Log> {
  return apiUpload<Log>(`/activities/${activityId}/logs`, uploadForm('save', file, operatorId))
}

function uploadForm(mode: 'preview' | 'save', file: File, operatorId: number | null): FormData {
  const form = new FormData()
  form.append('mode', mode)
  form.append('file', file)
  if (operatorId !== null) form.append('operatorId', String(operatorId))
  return form
}

export function isAdifFileName(name: string): boolean {
  return /\.(adi|adif)$/i.test(name)
}

export function problemText(problem: Problem): string {
  return problem.problem === 'missing' ? `falta ${problem.field}` : `${problem.field} con un valor incorrecto`
}

export function invalidRecordText(record: Summary['invalid'][number]): string {
  const who = record.callSign ? ` (${record.callSign})` : ''
  return `Registro ${record.record}${who}: ${record.problems.map(problemText).join(', ')}.`
}

/**
 * The warnings in words. The station warnings come together for each STATION_CALLSIGN value.
 */
export function warningTexts(summary: Pick<Summary, 'warnings' | 'operator'>): string[] {
  const texts: string[] = []
  const stations = new Map<string, number>()
  for (const warning of summary.warnings) {
    if (warning.kind === 'date') {
      texts.push(`Registro ${warning.record} (${warning.callSign}): la fecha ${warning.value} está fuera de la actividad.`)
    } else {
      stations.set(warning.value, (stations.get(warning.value) ?? 0) + 1)
    }
  }
  for (const [station, count] of stations) {
    const records = count === 1 ? '1 registro' : `${count} registros`
    texts.push(
      `STATION_CALLSIGN es ${station} en ${records}. No es el indicativo del operador ${summary.operator.callSign}.`,
    )
  }
  return texts
}

/**
 * The frequency in MHz, or the band.
 */
export function frequencyText(contact: Pick<Contact, 'frequency' | 'band'>): string {
  if (contact.frequency) return `${contact.frequency} MHz`
  return contact.band ?? ''
}
