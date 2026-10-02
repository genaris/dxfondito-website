import { apiGet, apiSend, apiUpload, apiUploadBlob, apiUrl } from './api.ts'

export const FIELD_NAMES = ['call_sign', 'name', 'date', 'time', 'frequency', 'mode', 'rst'] as const
export type FieldName = (typeof FIELD_NAMES)[number]

export const FONTS = ['sans', 'sans-bold', 'serif', 'serif-bold', 'mono-bold'] as const
export type Font = (typeof FONTS)[number]
export type Align = 'left' | 'center' | 'right'

/** The box of a field in pixels of the image, as the API keeps it. */
export interface Box {
  x: number
  y: number
  width: number
  height: number
}

export interface Field extends Box {
  colour: string
  align: Align
  font: Font
}

/** The fields of a template, by name: the QSL card fields or the certificate fields. */
export type Fields = Record<string, Field>

/** The fields of a kind of template, for the template editor. */
export interface TemplateKind {
  names: readonly string[]
  labels: Record<string, string>
  /** The example data of the sample image of the API. */
  sample: Record<string, string>
  defaultFields: (width: number, height: number) => Fields
  /** True if the editor searches the field boxes of the image (FR-QSL-12). */
  detect: boolean
}

export interface QslTemplate {
  activityId: number
  operator: { id: number; callSign: string }
  fields: Fields
  width: number
  height: number
  canEdit: boolean
}

export const FIELD_LABELS: Record<FieldName, string> = {
  call_sign: 'Indicativo',
  name: 'Nombre',
  date: 'Fecha',
  time: 'Hora (UTC)',
  frequency: 'Frecuencia',
  mode: 'Modo',
  rst: 'RST',
}

export const FONT_LABELS: Record<Font, string> = {
  sans: 'Sans',
  'sans-bold': 'Sans negrita',
  serif: 'Serif',
  'serif-bold': 'Serif negrita',
  'mono-bold': 'Mono negrita',
}

/** The example data of the API for the sample image (FR-QSL-5). */
export const SAMPLE: Record<FieldName, string> = {
  call_sign: 'LU1ABC/P',
  name: 'Juana Pérez',
  date: '04/10/2026',
  time: '14:30',
  frequency: '7.130 MHz',
  mode: 'SSB',
  rst: '59',
}

export const MAX_IMAGE_BYTES = 10 * 1024 * 1024
export const MAX_SIDE = 4096

export function readTemplates(activityId: number): Promise<QslTemplate[]> {
  return apiGet<QslTemplate[]>(`/activities/${activityId}/qsl-templates`)
}

export function templateImageUrl(activityId: number, operatorId: number, version = 0): string {
  // The version changes the address after a save. Thus the browser does not show an old image.
  return apiUrl(`/activities/${activityId}/qsl-templates/${operatorId}/image`, { v: String(version) })
}

export function saveTemplate(activityId: number, operatorId: number, fields: Fields, file: File | null): Promise<QslTemplate> {
  const form = new FormData()
  form.append('fields', JSON.stringify(fields))
  if (file) form.append('file', file)
  return apiUpload<QslTemplate>(`/activities/${activityId}/qsl-templates/${operatorId}`, form)
}

export function deleteTemplate(activityId: number, operatorId: number): Promise<unknown> {
  return apiSend('DELETE', `/activities/${activityId}/qsl-templates/${operatorId}`)
}

/**
 * The sample image of the API, as an object URL. The caller revokes it.
 */
export async function previewTemplate(fields: Fields, file: File | null, activityId: number, operatorId: number): Promise<string> {
  const form = new FormData()
  form.append('fields', JSON.stringify(fields))
  if (file) {
    form.append('file', file)
  } else {
    form.append('activityId', String(activityId))
    form.append('operatorId', String(operatorId))
  }
  return URL.createObjectURL(await apiUploadBlob('/template-preview', form))
}

/** The QSL card of one contact of a participant (D-27). */
export function qslCardUrl(baseCallSign: string, contactId: number): string {
  return apiUrl(`/participants/${baseCallSign}/qsl/${contactId}`)
}

/** The name of the downloaded file, such as QSL_LU1ABC_DPS-01_20261004_1430.jpg. */
export function qslFileName(baseCallSign: string, referenceCode: string, qsoAt: string): string {
  return `QSL_${baseCallSign}_${referenceCode}_${qsoAt.slice(0, 10).replaceAll('-', '')}_${qsoAt.slice(11, 16).replace(':', '')}.jpg`
}

export function fontUrl(font: Font): string {
  return apiUrl(`/fonts/${font}`)
}

export const MIN_WIDTH = 10
export const MIN_HEIGHT = 6
export const MAX_HEIGHT = 500

// The place of the text in the box. The API uses the same rules (api/src/Templates/TextBox.php).
const HEIGHT_TO_SIZE = 1.2
const CAP_HEIGHT = 0.72
const FIT = 0.97

/** The font size in pixels for a text with this width at the full size of the box. */
export function textSize(box: Box, fullTextWidth: number): number {
  let size = box.height / HEIGHT_TO_SIZE
  if (fullTextWidth > box.width && fullTextWidth > 0) size *= (box.width * FIT) / fullTextWidth
  return size
}

/** The full size of the text: the size before a wide text becomes smaller. */
export function fullSize(box: Box): number {
  return box.height / HEIGHT_TO_SIZE
}

/** The base line that puts the capital letters in the vertical centre of the box. */
export function baseline(box: Box, size: number): number {
  return Math.round(box.y + (box.height + CAP_HEIGHT * size) / 2)
}

/** The x position of the text for the SVG text-anchor of the alignment. */
export function anchorX(box: Box, align: Align): number {
  if (align === 'center') return box.x + box.width / 2
  if (align === 'right') return box.x + box.width
  return box.x
}

/**
 * The first boxes of the fields of a new template. A wide image gets two columns. The space between the rows
 * leaves room for the names of the fields above the boxes.
 */
export function defaultFields(width: number, height: number): Fields {
  const columns = width / height > 1.6 ? 2 : 1
  const rows = Math.ceil(FIELD_NAMES.length / columns)
  const top = Math.round(height * 0.06)
  const step = Math.floor((height * 0.9) / rows)
  const boxHeight = Math.min(MAX_HEIGHT, Math.max(MIN_HEIGHT, Math.round(step * 0.55)))
  const boxWidth = Math.max(MIN_WIDTH, Math.round(width * (columns === 2 ? 0.3 : 0.45)))
  const fields = {} as Fields
  FIELD_NAMES.forEach((name, index) => {
    const column = index % columns
    const row = Math.floor(index / columns)
    fields[name] = {
      x: Math.round(width * 0.06) + column * Math.round(width * 0.36),
      y: Math.min(height - boxHeight, top + row * step + (step - boxHeight)),
      width: boxWidth,
      height: boxHeight,
      colour: '#1A1A1A',
      align: 'left',
      font: name === 'call_sign' ? 'sans-bold' : 'sans',
    }
  })
  return fields
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, Math.round(value)))
}

/** Moves a box and keeps it in the image. */
export function moveBox(box: Box, dx: number, dy: number, imageWidth: number, imageHeight: number): Box {
  return {
    ...box,
    x: clamp(box.x + dx, 0, imageWidth - box.width),
    y: clamp(box.y + dy, 0, imageHeight - box.height),
  }
}

/** The handles of a box: the corners and the sides, as compass directions. */
export const HANDLES = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'] as const
export type Handle = (typeof HANDLES)[number]

/**
 * Changes the size of a box with a handle. The box stays in the image and keeps its minimum size.
 */
export function resizeBox(box: Box, handle: Handle, dx: number, dy: number, imageWidth: number, imageHeight: number): Box {
  let { x, y, width, height } = box
  const right = x + width
  const bottom = y + height
  if (handle.includes('w')) {
    x = clamp(x + dx, 0, right - MIN_WIDTH)
    width = right - x
  }
  if (handle.includes('e')) {
    width = clamp(width + dx, MIN_WIDTH, imageWidth - x)
  }
  if (handle.includes('n')) {
    y = clamp(y + dy, Math.max(0, bottom - MAX_HEIGHT), bottom - MIN_HEIGHT)
    height = bottom - y
  }
  if (handle.includes('s')) {
    height = clamp(height + dy, MIN_HEIGHT, Math.min(MAX_HEIGHT, imageHeight - y))
  }
  return { x, y, width, height }
}

/** Keeps a box in the image, for example after a change to a smaller image. */
export function fitBox(box: Box, imageWidth: number, imageHeight: number): Box {
  const width = clamp(box.width, MIN_WIDTH, imageWidth)
  const height = clamp(box.height, MIN_HEIGHT, Math.min(MAX_HEIGHT, imageHeight))
  return {
    x: clamp(box.x, 0, imageWidth - width),
    y: clamp(box.y, 0, imageHeight - height),
    width,
    height,
  }
}

/** The QSL card templates, for the template editor. */
export const QSL_KIND: TemplateKind = {
  names: FIELD_NAMES,
  labels: FIELD_LABELS,
  sample: SAMPLE,
  defaultFields,
  detect: true,
}
