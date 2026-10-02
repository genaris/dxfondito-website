import { describe, expect, it } from 'vitest'
import { anchorX, baseline, defaultFields, FIELD_NAMES, fitBox, moveBox, qslCardUrl, qslFileName, resizeBox, textSize } from './qsl.ts'

describe('the text in the box (the same values as TextBoxTest.php)', () => {
  it('fills the height of the box', () => {
    expect(textSize({ x: 0, y: 0, width: 400, height: 60 }, 200)).toBeCloseTo(50)
  })

  it('becomes smaller for a wide text', () => {
    expect(textSize({ x: 0, y: 0, width: 250, height: 60 }, 500)).toBeCloseTo(25 * 0.97)
  })

  it('puts the capital letters in the vertical centre', () => {
    expect(baseline({ x: 0, y: 100, width: 10, height: 60 }, 50)).toBe(148)
  })

  it('aligns the text in the box', () => {
    const box = { x: 10, y: 0, width: 200, height: 20 }
    expect([anchorX(box, 'left'), anchorX(box, 'center'), anchorX(box, 'right')]).toEqual([10, 110, 210])
  })
})

describe('defaultFields', () => {
  it('gives all boxes inside the image', () => {
    const fields = defaultFields(1200, 800)
    expect(Object.keys(fields)).toEqual([...FIELD_NAMES])
    for (const field of Object.values(fields)) {
      expect(field.x + field.width).toBeLessThanOrEqual(1200)
      expect(field.y + field.height).toBeLessThanOrEqual(800)
    }
  })

  it('gives a bold call sign', () => {
    expect(defaultFields(1200, 800).call_sign.font).toBe('sans-bold')
  })

  it('gives two columns of boxes that do not overlap for a wide image', () => {
    const fields = Object.values(defaultFields(1198, 321))
    expect(new Set(fields.map((field) => field.x)).size).toBe(2)
    for (const [index, a] of fields.entries()) {
      for (const b of fields.slice(index + 1)) {
        const apart = a.x + a.width <= b.x || b.x + b.width <= a.x || a.y + a.height <= b.y || b.y + b.height <= a.y
        expect(apart).toBe(true)
      }
    }
  })

  it('keeps the boxes inside a low image', () => {
    for (const field of Object.values(defaultFields(1198, 321))) {
      expect(field.y + field.height).toBeLessThanOrEqual(321)
      expect(field.y).toBeGreaterThanOrEqual(0)
    }
  })
})

describe('moveBox', () => {
  it('keeps the box in the image', () => {
    const box = { x: 10, y: 10, width: 100, height: 50 }
    expect(moveBox(box, -50, 900, 400, 300)).toEqual({ x: 0, y: 250, width: 100, height: 50 })
  })
})

describe('resizeBox', () => {
  const box = { x: 100, y: 100, width: 200, height: 50 }

  it('changes the right and bottom sides with the south-east corner', () => {
    expect(resizeBox(box, 'se', 30, 20, 1000, 1000)).toEqual({ x: 100, y: 100, width: 230, height: 70 })
  })

  it('keeps the right side with the west side', () => {
    expect(resizeBox(box, 'w', -40, 0, 1000, 1000)).toEqual({ x: 60, y: 100, width: 240, height: 50 })
  })

  it('keeps the minimum size', () => {
    expect(resizeBox(box, 'nw', 500, 500, 1000, 1000)).toEqual({ x: 290, y: 144, width: 10, height: 6 })
  })

  it('keeps the box in the image', () => {
    expect(resizeBox(box, 'se', 5000, 5000, 400, 300)).toEqual({ x: 100, y: 100, width: 300, height: 200 })
  })
})

describe('fitBox', () => {
  it('moves and cuts a box for a smaller image', () => {
    expect(fitBox({ x: 900, y: 50, width: 400, height: 40 }, 600, 300)).toEqual({ x: 200, y: 50, width: 400, height: 40 })
    expect(fitBox({ x: 0, y: 0, width: 900, height: 40 }, 600, 300)).toEqual({ x: 0, y: 0, width: 600, height: 40 })
  })
})

describe('QSL card of a contact', () => {
  it('gives the address and the name of the file', () => {
    expect(qslCardUrl('LU1ABC', 188)).toBe('api/index.php?r=/participants/LU1ABC/qsl/188')
    expect(qslFileName('LU1ABC', 'DPS-05', '2026-10-04T14:30:00Z')).toBe('QSL_LU1ABC_DPS-05_20261004_1430.jpg')
  })
})
