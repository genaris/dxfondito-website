import { activityPeriod } from './activities.ts'
import type { Activity } from './activities.ts'

// The texts of the program "Diplomas Puestos de Salud". The source is the page of LU2AOZ on QRZ.com.
// Change them here for each season. The activities and their dates come from the API.

export const PROGRAM = {
  group: 'Grupo DX Fondito',
  groupShort: 'GDxF',
  name: 'Puestos de Salud',
  tagline: 'QSL Especial & Diploma',
  purpose:
    'Reconocemos la labor de los médicos, enfermeros y practicantes de las Estaciones Saludables ' +
    'de los parques y plazas de la Ciudad de Buenos Aires. Cada Puesto de Salud y cada efeméride ' +
    'de la salud tiene su referencia, y cada contacto suma para el diploma.',
  stations:
    'Las Estaciones Saludables no solo nos cuidan: también dan asesoría nutricional, actividad física ' +
    'al aire libre, cumpleaños saludables y entretenimientos para niños, adultos y mayores.',
  season:
    'La temporada 2026 empezó el sábado 4 de julio y termina en diciembre. La idea es cubrir 20 eventos ' +
    'entre Puestos de Salud en los parques y efemérides.',
  bands: '2 m (145,750 MHz y la frecuencia de encuentro), 40 m y 80 m',
  modes: 'Solo fonía',
  hours: '13:00 a 20:00 UTC',
  power: 'Por la mañana intentamos transmitir en QRP, como ejercicio y para experimentar.',
  schedule:
    'Cada actividad indica sus horarios. En los parques dependen del Puesto de Salud y la activación ' +
    'se puede suspender por mal tiempo.',
  utc: 'Todos los contactos se cargan en horario UTC.',
  qsl:
    'Por cada activación se envía en forma virtual la QSL Especial confirmatoria: una sola por cada ' +
    'Puesto de Salud contactado. También se confirma con QSL estándar en las plataformas habituales.',
  qslName:
    'El nombre de la QSL es el de los listados oficiales de licencias de ENACOM (Argentina) y URSEC (Uruguay). ' +
    'Para otros países, la QSL no lleva nombre.',
  listeners: 'Pueden participar los radioescuchas que envíen el log de cada contacto.',
  certificateRule:
    'Cuentan las referencias distintas contactadas en el año. Los contactos con distintos operadores ' +
    'en la misma actividad no suman más.',
  activators: 'De a poco se van sumando al equipo de activadores.',
  facebook: { text: 'Grupo en Facebook', url: 'https://www.facebook.com/groups/1097332786007967' },
} as const

/** A planned event of the season: a Puesto de Salud in a park (DPS) or an efemérides of health (EFE). */
export interface CalendarEvent {
  /** YYYY-MM-DD. */
  date: string
  series: 'DPS' | 'EFE'
  name: string
}

/**
 * The planned calendar of the season, from the page of LU2AOZ on QRZ.com. The dates can change, and the group can
 * add more efemérides. The confirmed activities come from the API.
 */
export const CALENDAR: { season: number; events: CalendarEvent[] } = {
  season: 2026,
  events: [
    { date: '2026-07-04', series: 'EFE', name: 'Día Nacional del Médico Rural' },
    { date: '2026-07-12', series: 'EFE', name: 'Día Nacional de la Medicina Social' },
    { date: '2026-07-14', series: 'EFE', name: 'Día Mundial del Auxiliar de Enfermería' },
    { date: '2026-08-23', series: 'DPS', name: 'Puesto de Salud Parque Chacabuco' },
    { date: '2026-08-30', series: 'DPS', name: 'Puesto de Salud Parque Patricios' },
    { date: '2026-09-13', series: 'DPS', name: 'Puesto de Salud Rosedal' },
    { date: '2026-09-22', series: 'EFE', name: 'Dr. Luis Agote' },
    { date: '2026-09-27', series: 'DPS', name: 'Puesto de Salud Parque Centenario' },
    { date: '2026-10-04', series: 'DPS', name: 'Puesto de Salud Parque Rivadavia' },
    { date: '2026-10-11', series: 'DPS', name: 'Puesto de Salud Parque Saavedra' },
    { date: '2026-10-12', series: 'EFE', name: 'Día del Farmacéutico Argentino' },
    { date: '2026-10-13', series: 'EFE', name: 'Día del Psicólogo' },
    { date: '2026-10-20', series: 'EFE', name: 'Día del Pediatra' },
    { date: '2026-11-08', series: 'EFE', name: 'Día Mundial de la Radiología' },
    { date: '2026-11-21', series: 'EFE', name: 'Día del Enfermero' },
    { date: '2026-11-22', series: 'EFE', name: 'Natalicio de la Dra. Cecilia Grierson' },
    { date: '2026-12-03', series: 'EFE', name: 'Día del Médico' },
    { date: '2026-12-13', series: 'EFE', name: 'Dr. Julio César Palmaz' },
  ],
}

/** The names of the certificate levels (R-CER-1). */
const LEVEL_NAMES: Record<number, string> = { 5: 'Bronce', 10: 'Plata', 15: 'Oro' }

export function levelName(points: number): string {
  return LEVEL_NAMES[points] ?? `${points} puntos`
}

/** A CSS class for the color of the medal of a level. */
export function levelClass(points: number): string {
  return `medal medal-${(LEVEL_NAMES[points] ?? 'other').toLowerCase()}`
}

type Schedule = Pick<Activity, 'startDate' | 'endDate' | 'startTime' | 'endTime'>

/**
 * The activity in progress or the next one: the earliest activity that has not ended.
 * An activity without hours ends at the end of its last day.
 *
 * @param now YYYY-MM-DDTHH:MM in UTC.
 */
export function nextActivity<T extends Schedule>(activities: T[], now: string): T | null {
  const open = activities.filter((activity) => activityPeriod(activity).end >= now)
  open.sort((a, b) => activityPeriod(a).start.localeCompare(activityPeriod(b).start))
  return open[0] ?? null
}

/**
 * The state of the next activity: on the air now, later today, or on a later day.
 *
 * @param now YYYY-MM-DDTHH:MM in UTC.
 */
export function activityState(activity: Schedule, now: string): 'live' | 'today' | 'later' {
  const { start, end } = activityPeriod(activity)
  if (start <= now && now <= end) return 'live'
  return activity.startDate === now.slice(0, 10) ? 'today' : 'later'
}

/** Now in UTC, as YYYY-MM-DDTHH:MM. */
export function nowUtc(now: Date = new Date()): string {
  return now.toISOString().slice(0, 16)
}

/** Today in UTC, as YYYY-MM-DD. */
export function todayUtc(now: Date = new Date()): string {
  return now.toISOString().slice(0, 10)
}

const WEEKDAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado']
const MONTHS = [
  'enero',
  'febrero',
  'marzo',
  'abril',
  'mayo',
  'junio',
  'julio',
  'agosto',
  'septiembre',
  'octubre',
  'noviembre',
  'diciembre',
]

/** A date in words, such as "domingo 4 de octubre". */
export function longDate(date: string): string {
  const [year, month, day] = date.split('-').map(Number)
  const weekday = new Date(Date.UTC(year, month - 1, day)).getUTCDay()
  return `${WEEKDAYS[weekday]} ${day} de ${MONTHS[month - 1]}`
}
