import { describe, expect, it } from 'vitest'
import { certificateFileName, certificateUrl, defaultCertificateFields } from './certificates.ts'

describe('defaultCertificateFields', () => {
  it('puts the call sign in the centre, above the date', () => {
    const { call_sign, date } = defaultCertificateFields(1600, 1131)
    expect(call_sign.x + call_sign.width / 2).toBe(800)
    expect(date.x + date.width / 2).toBe(800)
    expect(call_sign.y + call_sign.height).toBeLessThan(date.y)
    expect(call_sign.align).toBe('center')
  })

  it('keeps the boxes in the image and in the limits of the API', () => {
    for (const field of Object.values(defaultCertificateFields(4000, 4096))) {
      expect(field.height).toBeLessThanOrEqual(500)
      expect(field.x + field.width).toBeLessThanOrEqual(4000)
      expect(field.y + field.height).toBeLessThanOrEqual(4096)
    }
  })
})

describe('certificate addresses', () => {
  it('gives the address of the PDF file', () => {
    expect(certificateUrl('LU1ABC', 2026, 5)).toBe('api/index.php?r=/participants/LU1ABC/certificates/2026/5')
  })

  it('gives the name of the file', () => {
    expect(certificateFileName('LU1ABC', 2026, 'Bronce')).toBe('Certificado_LU1ABC_2026_Bronce.pdf')
  })
})
