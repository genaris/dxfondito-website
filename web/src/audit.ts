import { apiGet } from './api.ts'
import { ROLE_NAMES } from './accounts.ts'
import type { Role } from './accounts.ts'

export interface AuditEntry {
  id: number
  user: { id: number; callSign: string }
  action: string
  entityId: number | null
  detail: Record<string, unknown> | null
  /** UTC, such as 2026-10-01T12:00:00Z. */
  createdAt: string
}

export interface AuditPage {
  entries: AuditEntry[]
  page: number
  pages: number
}

export function readAudit(page: number): Promise<AuditPage> {
  return apiGet<AuditPage>('/audit', { page: String(page) })
}

const ACTION_NAMES: Record<string, string> = {
  'user.create': 'Creó una cuenta',
  'user.update': 'Cambió una cuenta',
  'user.password.reset': 'Puso una contraseña inicial',
  'user.password.change': 'Cambió su contraseña',
  'reference.create': 'Creó una referencia',
  'reference.update': 'Cambió una referencia',
  'reference.delete': 'Borró una referencia',
  'activity.create': 'Creó una actividad',
  'activity.update': 'Cambió una actividad',
  'activity.delete': 'Borró una actividad',
  'log.upload': 'Subió un log',
  'log.delete': 'Borró un log',
  'qsl-template.save': 'Guardó una plantilla QSL',
  'qsl-template.delete': 'Borró una plantilla QSL',
  'certificate-template.save': 'Guardó una plantilla de certificado',
  'certificate-template.delete': 'Borró una plantilla de certificado',
  'registry.update': 'Actualizó un listado de licencias',
}

const FIELD_NAMES: Record<string, string> = {
  name: 'Nombre',
  email: 'Email',
  role: 'Rol',
  active: 'Estado',
  code: 'Código',
  description: 'Descripción',
  reference: 'Referencia',
  startDate: 'Inicio',
  endDate: 'Fin',
  startTime: 'Hora de inicio',
  endTime: 'Hora de fin',
  fileName: 'Archivo',
  operator: 'Operador',
  contacts: 'Contactos',
  season: 'Temporada',
  points: 'Puntos',
  country: 'País',
  licensees: 'Licencias',
}

export function actionName(action: string): string {
  return ACTION_NAMES[action] ?? action
}

/**
 * The date and the time in UTC, as all times of the system (C-7).
 */
export function formatUtc(createdAt: string): string {
  return `${createdAt.slice(0, 10)} ${createdAt.slice(11, 16)} UTC`
}

function formatValue(field: string, value: unknown): string {
  if (value === null || value === '') return '(vacío)'
  if (field === 'role' && typeof value === 'string' && value in ROLE_NAMES) return ROLE_NAMES[value as Role]
  if (field === 'active') return value ? 'activa' : 'desactivada'
  return String(value)
}

/**
 * The lines that explain the detail of an entry.
 */
export function detailLines(entry: AuditEntry): string[] {
  const detail = entry.detail ?? {}
  const lines: string[] = []

  if (typeof detail.callSign === 'string' && entry.action !== 'user.password.change') {
    lines.push(`Cuenta ${detail.callSign}`)
  }
  if (typeof detail.code === 'string') lines.push(`Referencia ${detail.code}`)
  if (typeof detail.label === 'string') lines.push(`Actividad ${detail.label}`)

  const createdFields: Record<string, string[]> = {
    'user.create': ['name', 'email', 'role'],
    'reference.create': ['name', 'description'],
    'reference.delete': ['name'],
    'activity.create': ['name', 'startTime', 'endDate', 'endTime', 'description'],
    'activity.delete': ['name'],
    'log.upload': ['fileName', 'operator', 'contacts'],
    'log.delete': ['fileName', 'operator', 'contacts'],
    'qsl-template.save': ['operator'],
    'qsl-template.delete': ['operator'],
    'certificate-template.save': ['season', 'points'],
    'certificate-template.delete': ['season', 'points'],
    'registry.update': ['country', 'licensees'],
  }
  for (const field of createdFields[entry.action] ?? []) {
    if (field in detail) lines.push(`${FIELD_NAMES[field]}: ${formatValue(field, detail[field])}`)
  }
  if (detail.firstAdministrator === true) lines.push('Primer administrador, desde la página de migraciones')
  const changes = detail.changes
  if (changes && typeof changes === 'object') {
    for (const [field, pair] of Object.entries(changes as Record<string, unknown>)) {
      if (!Array.isArray(pair) || pair.length !== 2) continue
      const name = FIELD_NAMES[field] ?? field
      lines.push(`${name}: ${formatValue(field, pair[0])} → ${formatValue(field, pair[1])}`)
    }
  }
  return lines
}
