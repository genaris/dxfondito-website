import { apiGet, apiSend, apiUpload, apiUploadBlob, apiUrl } from './api.ts'
import { MAX_HEIGHT, MIN_HEIGHT } from './qsl.ts'
import type { Fields, TemplateKind } from './qsl.ts'

/** The certificate template of one level in one season (FR-CER-1). */
export interface CertificateTemplate {
  season: number
  points: number
  fields: Fields
  width: number
  height: number
}

export const CERTIFICATE_FIELD_NAMES = ['call_sign', 'date'] as const

export const CERTIFICATE_LABELS: Record<string, string> = {
  call_sign: 'Indicativo',
  date: 'Fecha',
}

/** The example data of the API for the sample image. */
export const CERTIFICATE_SAMPLE: Record<string, string> = {
  call_sign: 'LU1ABC',
  date: '4 de octubre de 2026',
}

/**
 * The first boxes of a new certificate template: a large call sign in the centre, and the date below it.
 */
export function defaultCertificateFields(width: number, height: number): Fields {
  const box = (top: number, boxWidth: number, boxHeight: number) => {
    const h = Math.min(MAX_HEIGHT, Math.max(MIN_HEIGHT, Math.round(height * boxHeight)))
    const w = Math.round(width * boxWidth)
    return { x: Math.round((width - w) / 2), y: Math.round(height * top), width: w, height: h }
  }
  return {
    call_sign: { ...box(0.42, 0.6, 0.11), colour: '#1A1A1A', align: 'center', font: 'serif-bold' },
    date: { ...box(0.7, 0.4, 0.045), colour: '#1A1A1A', align: 'center', font: 'serif' },
  }
}

export const CERTIFICATE_KIND: TemplateKind = {
  names: CERTIFICATE_FIELD_NAMES,
  labels: CERTIFICATE_LABELS,
  sample: CERTIFICATE_SAMPLE,
  defaultFields: defaultCertificateFields,
  detect: false,
}

export interface CertificateTemplates {
  /** The certificate levels, such as [5, 10, 15]. */
  levels: number[]
  templates: CertificateTemplate[]
}

export function readCertificateTemplates(): Promise<CertificateTemplates> {
  return apiGet<CertificateTemplates>('/certificate-templates')
}

export function certificateImageUrl(season: number, points: number, version = 0): string {
  // The version changes the address after a save. Thus the browser does not show an old image.
  return apiUrl(`/certificate-templates/${season}/${points}/image`, { v: String(version) })
}

export function saveCertificateTemplate(
  season: number,
  points: number,
  fields: Fields,
  file: File | null,
): Promise<CertificateTemplate> {
  const form = new FormData()
  form.append('fields', JSON.stringify(fields))
  if (file) form.append('file', file)
  return apiUpload<CertificateTemplate>(`/certificate-templates/${season}/${points}`, form)
}

export function deleteCertificateTemplate(season: number, points: number): Promise<unknown> {
  return apiSend('DELETE', `/certificate-templates/${season}/${points}`)
}

/**
 * The sample image of the API, as an object URL. The caller revokes it.
 */
export async function previewCertificate(fields: Fields, file: File | null, season: number, points: number): Promise<string> {
  const form = new FormData()
  form.append('fields', JSON.stringify(fields))
  if (file) {
    form.append('file', file)
  } else {
    form.append('season', String(season))
    form.append('points', String(points))
  }
  return URL.createObjectURL(await apiUploadBlob('/certificate-preview', form))
}

/** The certificate of a participant as a PDF file (FR-CER-5). */
export function certificateUrl(callSign: string, season: number, points: number): string {
  return apiUrl(`/participants/${callSign}/certificates/${season}/${points}`)
}

/** The name of the downloaded file, such as Certificado_LU1ABC_2026_Bronce.pdf. */
export function certificateFileName(callSign: string, season: number, levelName: string): string {
  return `Certificado_${callSign}_${season}_${levelName}.pdf`
}
