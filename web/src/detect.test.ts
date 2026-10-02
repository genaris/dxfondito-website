import { describe, expect, it } from 'vitest'
import { detectBoxes, fieldsFromBoxes, pixelColour, readingOrder, swapBoxes } from './detect.ts'
import type { Pixels, Rgb } from './detect.ts'
import { defaultFields } from './qsl.ts'
import type { Box } from './qsl.ts'

const WHITE: Rgb = { r: 250, g: 250, b: 250 }
const BLUE: Rgb = { r: 145, g: 216, b: 246 }
const GREEN: Rgb = { r: 60, g: 170, b: 80 }

function image(width: number, height: number, background: Rgb = WHITE): Pixels {
  const data = new Uint8ClampedArray(width * height * 4)
  for (let p = 0; p < width * height; p++) data.set([background.r, background.g, background.b, 255], p * 4)
  return { data, width, height }
}

function paint(pixels: Pixels, box: Box, colour: Rgb): void {
  for (let y = box.y; y < box.y + box.height; y++) {
    for (let x = box.x; x < box.x + box.width; x++) pixels.data.set([colour.r, colour.g, colour.b], (y * pixels.width + x) * 4)
  }
}

/** Seven boxes in one row near the bottom, as on the template DPS-01. */
const ROW: Box[] = [
  { x: 97, y: 950, width: 174, height: 42 },
  { x: 280, y: 951, width: 174, height: 42 },
  { x: 463, y: 951, width: 293, height: 42 },
  { x: 763, y: 950, width: 153, height: 42 },
  { x: 926, y: 950, width: 153, height: 42 },
  { x: 1088, y: 950, width: 71, height: 42 },
  { x: 1170, y: 950, width: 71, height: 42 },
]

describe('detectBoxes', () => {
  it('finds the boxes of one colour in the order of reading', () => {
    const pixels = image(1583, 1061)
    for (const box of [...ROW].reverse()) paint(pixels, box, BLUE)
    const detection = detectBoxes(pixels)
    expect(detection.boxes).toEqual(ROW)
    expect(detection.colour).toEqual(BLUE)
  })

  it('accepts the small changes of colour of a JPEG file', () => {
    const pixels = image(1583, 1061)
    for (const box of ROW) paint(pixels, box, BLUE)
    paint(pixels, { x: 100, y: 960, width: 20, height: 10 }, { r: 160, g: 200, b: 255 })
    expect(detectBoxes(pixels).boxes).toEqual(ROW)
  })

  it('prefers the colour with more boxes to a large area of one colour', () => {
    const pixels = image(1000, 800)
    paint(pixels, { x: 0, y: 450, width: 1000, height: 350 }, GREEN)
    const boxes = [0, 1, 2].map((i) => ({ x: 50 + i * 300, y: 650, width: 200, height: 40 }))
    for (const box of boxes) paint(pixels, box, BLUE)
    expect(detectBoxes(pixels).boxes).toEqual(boxes)
  })

  it('ignores the boxes of the upper part', () => {
    const pixels = image(1000, 800)
    paint(pixels, { x: 50, y: 100, width: 200, height: 40 }, BLUE)
    paint(pixels, { x: 50, y: 600, width: 200, height: 40 }, BLUE)
    expect(detectBoxes(pixels).boxes).toEqual([{ x: 50, y: 600, width: 200, height: 40 }])
  })

  it('ignores the areas that are not filled boxes', () => {
    const pixels = image(1000, 800)
    // A frame, as the outline of a letter: large, but nearly empty.
    paint(pixels, { x: 50, y: 500, width: 300, height: 4 }, BLUE)
    paint(pixels, { x: 50, y: 500, width: 4, height: 200 }, BLUE)
    paint(pixels, { x: 500, y: 600, width: 200, height: 40 }, BLUE)
    expect(detectBoxes(pixels).boxes).toEqual([{ x: 500, y: 600, width: 200, height: 40 }])
  })

  it('finds no boxes on a template with grey boxes', () => {
    const pixels = image(1000, 800)
    paint(pixels, { x: 50, y: 600, width: 200, height: 40 }, { r: 200, g: 200, b: 200 })
    expect(detectBoxes(pixels)).toEqual({ boxes: [], colour: null })
  })

  it('finds grey boxes with a colour of the user', () => {
    const grey = { r: 200, g: 200, b: 200 }
    const pixels = image(1000, 800)
    paint(pixels, { x: 50, y: 600, width: 200, height: 40 }, grey)
    expect(detectBoxes(pixels, pixelColour(pixels, 60, 610)).boxes).toEqual([{ x: 50, y: 600, width: 200, height: 40 }])
  })
})

describe('readingOrder', () => {
  it('puts the boxes in rows, and from left to right in each row', () => {
    const a = { x: 500, y: 600, width: 100, height: 40 }
    const b = { x: 50, y: 604, width: 100, height: 40 }
    const c = { x: 300, y: 700, width: 100, height: 40 }
    const d = { x: 40, y: 702, width: 100, height: 40 }
    expect(readingOrder([a, c, d, b])).toEqual([b, a, d, c])
  })
})

describe('fieldsFromBoxes', () => {
  const fields = defaultFields(1583, 1061)

  it('gives the boxes to the fields in the usual order of a QSL card', () => {
    const result = fieldsFromBoxes(ROW, fields, 1583, 1061)
    expect(result?.date).toMatchObject({ ...ROW[0], align: 'center', font: 'sans' })
    expect(result?.call_sign).toMatchObject({ ...ROW[1], align: 'center', font: 'sans-bold' })
    expect(result?.rst).toMatchObject(ROW[6])
  })

  it('gives no result without one box for each field', () => {
    expect(fieldsFromBoxes(ROW.slice(0, 6), fields, 1583, 1061)).toBeNull()
  })

  it('keeps a high box in the limits of the API', () => {
    const boxes = ROW.map((box) => ({ ...box, y: 300, height: 700 }))
    expect(fieldsFromBoxes(boxes, fields, 1583, 1061)?.date.height).toBe(500)
  })
})

describe('swapBoxes', () => {
  it('exchanges the boxes and keeps the other values', () => {
    const fields = fieldsFromBoxes(ROW, defaultFields(1583, 1061), 1583, 1061)!
    const result = swapBoxes(fields, 'frequency', 'time')
    expect(result.frequency).toEqual({ ...fields.frequency, ...ROW[4] })
    expect(result.time).toEqual({ ...fields.time, ...ROW[3] })
  })
})
