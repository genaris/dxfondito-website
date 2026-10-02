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
  power: 'Por la mañana se transmite en QRP, como ejercicio y para experimentar.',
  schedule:
    'Cada actividad indica sus horarios. En los parques dependen del Puesto de Salud y la activación ' +
    'se puede suspender por mal tiempo.',
  utc: 'Todos los contactos se cargan en horario UTC.',
  qsl:
    'Por cada activación se envía en forma virtual la QSL Especial confirmatoria: una sola por cada ' +
    'Puesto de Salud contactado. También se confirma con QSL estándar en las plataformas habituales.',
  listeners: 'Pueden participar los radioescuchas que envíen el log de cada contacto.',
  certificateRule:
    'Cuentan las referencias distintas contactadas en el año. Los contactos con distintos operadores ' +
    'en la misma actividad no suman más.',
  source: { text: 'LU2AOZ en QRZ.com', url: 'https://www.qrz.com/db/LU2AOZ' },
} as const

/** The names of the certificate levels (R-CER-1). */
const LEVEL_NAMES: Record<number, string> = { 5: 'Bronce', 10: 'Plata', 15: 'Oro' }

export function levelName(points: number): string {
  return LEVEL_NAMES[points] ?? `${points} puntos`
}

/** A CSS class for the color of the medal of a level. */
export function levelClass(points: number): string {
  return `medal medal-${(LEVEL_NAMES[points] ?? 'other').toLowerCase()}`
}

/**
 * The activity in progress or the next one: the earliest activity that has not ended.
 *
 * @param today YYYY-MM-DD in UTC.
 */
export function nextActivity<T extends Pick<Activity, 'startDate' | 'endDate'>>(activities: T[], today: string): T | null {
  const open = activities.filter((activity) => activity.endDate >= today)
  open.sort((a, b) => a.startDate.localeCompare(b.startDate))
  return open[0] ?? null
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
