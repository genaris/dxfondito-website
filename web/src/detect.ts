import { fitBox } from './qsl.ts'
import type { Box, FieldName, Fields } from './qsl.ts'

// The search of the field boxes of a QSL card template (FR-QSL-12). The source of the method is
// qsl-send (qsl_send/detect.py), which found the seven boxes of each template of the 2026 season.
//
// Most templates leave the write-in areas as flat rectangles of one saturated colour, in the lower part of the card:
// 1. The most common saturated colours of the lower part are the candidates.
// 2. For each candidate, the pixels of that colour make connected areas. The filled areas of a box size are boxes.
// 3. The colour with the most boxes wins. A large background of one colour has more pixels than seven boxes,
//    but fewer boxes.
// 4. The boxes go in rows from top to bottom, and from left to right in each row: the order of reading.

/** The pixels of an image, as the ImageData of a canvas gives them: RGBA, 4 bytes for each pixel. */
export interface Pixels {
  data: Uint8ClampedArray
  width: number
  height: number
}

export interface Rgb {
  r: number
  g: number
  b: number
}

export interface Detection {
  boxes: Box[]
  colour: Rgb | null
}

/** The search starts at this part of the height. The field boxes are almost always in the lower part. */
const SEARCH_TOP = 0.55
/** The largest difference of each channel from the colour of the boxes. JPEG files change the colours a little. */
const TOLERANCE = 26
const MIN_BOX_WIDTH = 40
const MIN_BOX_HEIGHT = 18
/** A grey or a white has a smaller difference between its largest and its smallest channel. */
const MIN_SATURATION = 25
/** The part of the area of a box that has the colour. Texts and drawings fill less. */
const MIN_FILL = 0.75
/** Areas smaller than this part of the median box are specks. */
const MIN_AREA_OF_MEDIAN = 0.15
const CANDIDATES = 6
const QUANTUM = 8
/** The width of the small copy for the choice of the colour. The boxes of the result come from the full image. */
const SCORE_WIDTH = 400

/**
 * The order of the fields on a usual QSL card: date, call sign ("QSO con"), name, frequency, time, mode and RST.
 * The search finds the boxes, not their labels. Thus this order gives each box its field.
 */
export const READING_ORDER: readonly FieldName[] = ['date', 'call_sign', 'name', 'frequency', 'time', 'mode', 'rst']

/**
 * Finds the field boxes of a template.
 *
 * @param colour The colour of the boxes, if the user chose it. Otherwise the search finds it.
 */
export function detectBoxes(pixels: Pixels, colour: Rgb | null = null): Detection {
  const shortlist = colour ? [colour] : candidateColours(pixels)
  if (shortlist.length === 0) return { boxes: [], colour: null }

  // The choice of the colour uses a small copy: the mask of each candidate on the full image takes most of the time.
  let best = shortlist[0]
  if (shortlist.length > 1) {
    const scale = Math.min(1, SCORE_WIDTH / pixels.width)
    const small = scale < 1 ? shrink(pixels, scale) : pixels
    const minWidth = Math.max(4, Math.round(MIN_BOX_WIDTH * scale))
    const minHeight = Math.max(3, Math.round(MIN_BOX_HEIGHT * scale))
    let bestScore = -1
    for (const candidate of shortlist) {
      const score = boxesOfColour(small, candidate, minWidth, minHeight).length
      if (score > bestScore) {
        bestScore = score
        best = candidate
      }
    }
  }
  return { boxes: readingOrder(boxesOfColour(pixels, best, MIN_BOX_WIDTH, MIN_BOX_HEIGHT)), colour: best }
}

/** The colour of one pixel. */
export function pixelColour(pixels: Pixels, x: number, y: number): Rgb {
  const i = (Math.floor(y) * pixels.width + Math.floor(x)) * 4
  return { r: pixels.data[i], g: pixels.data[i + 1], b: pixels.data[i + 2] }
}

/**
 * Gives the detected boxes to the fields, in the reading order. The fields keep their colour and font.
 * The text goes in the centre of each box. Only seven boxes, one for each field, give a result.
 */
export function fieldsFromBoxes(boxes: Box[], fields: Fields, width: number, height: number): Fields | null {
  if (boxes.length !== READING_ORDER.length) return null
  const result = { ...fields }
  READING_ORDER.forEach((name, index) => {
    result[name] = { ...fields[name], ...fitBox(boxes[index], width, height), align: 'center' }
  })
  return result
}

/** Exchanges the boxes of two fields. The fields keep their colour, alignment and font. */
export function swapBoxes(fields: Fields, a: string, b: string): Fields {
  const box = ({ x, y, width, height }: Box): Box => ({ x, y, width, height })
  return { ...fields, [a]: { ...fields[a], ...box(fields[b]) }, [b]: { ...fields[b], ...box(fields[a]) } }
}

/** The fields other than this one, for the exchange of boxes. */
export function otherFields(names: readonly string[], name: string): string[] {
  return names.filter((other) => other !== name)
}

function key(r: number, g: number, b: number): number {
  return (r << 16) | (g << 8) | b
}

/** The most common saturated colours of the lower part, most common first. */
function candidateColours({ data, width, height }: Pixels): Rgb[] {
  const top = Math.floor(height * SEARCH_TOP)
  const buckets = new Map<number, number>()
  forSample(width, height, top, (i) => {
    const r = data[i]
    const g = data[i + 1]
    const b = data[i + 2]
    if (Math.max(r, g, b) - Math.min(r, g, b) < MIN_SATURATION) return
    const bucket = key(quantise(r), quantise(g), quantise(b))
    buckets.set(bucket, (buckets.get(bucket) ?? 0) + 1)
  })
  const wanted = [...buckets.entries()]
    .sort((a, b) => b[1] - a[1])
    .slice(0, CANDIDATES)
    .map(([bucket]) => bucket)

  // The exact colour that is most common in each bucket. Thus the mask has its centre on the real colour of the boxes.
  const exact = new Map<number, Map<number, number>>(wanted.map((bucket) => [bucket, new Map()]))
  forSample(width, height, top, (i) => {
    const r = data[i]
    const g = data[i + 1]
    const b = data[i + 2]
    const counts = exact.get(key(quantise(r), quantise(g), quantise(b)))
    if (!counts) return
    const colour = key(r, g, b)
    counts.set(colour, (counts.get(colour) ?? 0) + 1)
  })
  return wanted.map((bucket) => {
    let best = bucket
    let most = 0
    for (const [colour, count] of exact.get(bucket) ?? []) {
      if (count > most) {
        most = count
        best = colour
      }
    }
    return { r: (best >> 16) & 255, g: (best >> 8) & 255, b: best & 255 }
  })
}

function quantise(value: number): number {
  return value - (value % QUANTUM)
}

/** Each second pixel of each second line, from the line `top`. */
function forSample(width: number, height: number, top: number, visit: (index: number) => void): void {
  for (let y = top; y < height; y += 2) {
    for (let x = 0; x < width; x += 2) visit((y * width + x) * 4)
  }
}

/** A small copy of the image, with the nearest pixel. */
function shrink(pixels: Pixels, scale: number): Pixels {
  const width = Math.max(1, Math.round(pixels.width * scale))
  const height = Math.max(1, Math.round(pixels.height * scale))
  const data = new Uint8ClampedArray(width * height * 4)
  for (let y = 0; y < height; y++) {
    const sourceY = Math.min(pixels.height - 1, Math.floor((y + 0.5) / scale))
    for (let x = 0; x < width; x++) {
      const sourceX = Math.min(pixels.width - 1, Math.floor((x + 0.5) / scale))
      const from = (sourceY * pixels.width + sourceX) * 4
      const to = (y * width + x) * 4
      data[to] = pixels.data[from]
      data[to + 1] = pixels.data[from + 1]
      data[to + 2] = pixels.data[from + 2]
      data[to + 3] = 255
    }
  }
  return { data, width, height }
}

/** The boxes of one colour in the lower part of the image. */
function boxesOfColour(pixels: Pixels, colour: Rgb, minWidth: number, minHeight: number): Box[] {
  // Only the lower part. The same colour is often in the drawings of the upper part, and they are never fields.
  const top = Math.floor(pixels.height * SEARCH_TOP)
  const boxes = components(mask(pixels, colour, top), pixels.width, pixels.height, minWidth, minHeight)
  if (boxes.length === 0) return boxes
  const areas = boxes.map((box) => box.width * box.height).sort((a, b) => a - b)
  const median = areas[Math.floor(areas.length / 2)]
  return boxes.filter((box) => box.width * box.height >= median * MIN_AREA_OF_MEDIAN)
}

/** 1 for each pixel near the colour, from the line `top`. */
function mask({ data, width, height }: Pixels, { r, g, b }: Rgb, top: number): Uint8Array {
  const result = new Uint8Array(width * height)
  for (let p = top * width; p < width * height; p++) {
    const i = p * 4
    if (Math.abs(data[i] - r) <= TOLERANCE && Math.abs(data[i + 1] - g) <= TOLERANCE && Math.abs(data[i + 2] - b) <= TOLERANCE) {
      result[p] = 1
    }
  }
  return result
}

/** The connected areas of the mask that are filled boxes. The search uses a stack: deep areas need no recursion. */
function components(marks: Uint8Array, width: number, height: number, minWidth: number, minHeight: number): Box[] {
  const boxes: Box[] = []
  const stack = new Int32Array(width * height)
  for (let start = 0; start < marks.length; start++) {
    if (marks[start] !== 1) continue
    marks[start] = 2
    let size = 0
    stack[size++] = start
    let left = width
    let right = 0
    let topY = height
    let bottom = 0
    let count = 0
    while (size > 0) {
      const p = stack[--size]
      const x = p % width
      const y = (p - x) / width
      count++
      if (x < left) left = x
      if (x > right) right = x
      if (y < topY) topY = y
      if (y > bottom) bottom = y
      if (x + 1 < width && marks[p + 1] === 1) {
        marks[p + 1] = 2
        stack[size++] = p + 1
      }
      if (x > 0 && marks[p - 1] === 1) {
        marks[p - 1] = 2
        stack[size++] = p - 1
      }
      if (y + 1 < height && marks[p + width] === 1) {
        marks[p + width] = 2
        stack[size++] = p + width
      }
      if (y > 0 && marks[p - width] === 1) {
        marks[p - width] = 2
        stack[size++] = p - width
      }
    }
    const boxWidth = right - left + 1
    const boxHeight = bottom - topY + 1
    if (boxWidth < minWidth || boxHeight < minHeight) continue
    if (count < MIN_FILL * boxWidth * boxHeight) continue
    boxes.push({ x: left, y: topY, width: boxWidth, height: boxHeight })
  }
  return boxes
}

/** The boxes in rows from top to bottom, and from left to right in each row. */
export function readingOrder(boxes: Box[]): Box[] {
  const sorted = [...boxes].sort((a, b) => a.y - b.y || a.x - b.x)
  const rows: Box[][] = []
  let bottom = -Infinity
  for (const box of sorted) {
    // A box that starts below the band of the current row starts a new row.
    if (rows.length === 0 || box.y > bottom - Math.max(4, Math.floor(box.height / 3))) {
      rows.push([box])
      bottom = box.y + box.height
    } else {
      rows[rows.length - 1].push(box)
      bottom = Math.max(bottom, box.y + box.height)
    }
  }
  return rows.flatMap((row) => row.sort((a, b) => a.x - b.x))
}
