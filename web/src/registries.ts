import { apiGet, apiSend } from './api.ts'

/** The last update of an official registry of licensees (FR-QSL-3a). */
export interface RegistryUpdate {
  country: string
  count: number
  sourceUrl: string
  updatedAt: string
}

/** The registries that the system reads: the call signs of Argentina and Uruguay. */
export const REGISTRIES = [
  { country: 'AR', name: 'Argentina', source: 'ENACOM', page: 'https://www.enacom.gob.ar/listado-de-radioaficionados_p316' },
  {
    country: 'UY',
    name: 'Uruguay',
    source: 'URSEC',
    page: 'https://www.gub.uy/unidad-reguladora-servicios-comunicaciones/tematica/radioaficionados',
  },
] as const

export function readRegistries(): Promise<RegistryUpdate[]> {
  return apiGet<RegistryUpdate[]>('/registries')
}

export function updateRegistry(country: string): Promise<{ country: string; count: number }> {
  return apiSend('POST', `/registries/${country.toLowerCase()}`)
}
