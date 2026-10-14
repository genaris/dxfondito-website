import { describe, expect, it } from 'vitest'
import { countEntities, countryOf, entityOf, entityTotal, nameOfKey, PROVINCE_NAMES, provinceOf } from './entities.ts'
import { MAP } from './mapShapes.ts'

describe('provinceOf', () => {
  it('reads the first letter of the suffix', () => {
    expect(provinceOf('LU2AOZ')).toBe('Ciudad de Buenos Aires')
    expect(provinceOf('LW3DMV')).toBe('Buenos Aires')
    expect(provinceOf('LU5FTM')).toBe('Santa Fe')
    expect(provinceOf('AZ1HAB')).toBe('Córdoba')
    expect(provinceOf('L21WAB')).toBe('Chubut')
  })

  it('reads the second letter for G and X', () => {
    expect(provinceOf('LU2GAC')).toBe('Chaco')
    expect(provinceOf('LU2GPC')).toBe('Formosa')
    expect(provinceOf('LU1XAA')).toBe('Santa Cruz')
    expect(provinceOf('LU1XZZ')).toBe('Tierra del Fuego')
  })

  it('gives null for a call sign of a different country', () => {
    expect(provinceOf('CX1ABC')).toBeNull()
  })
})

describe('countryOf', () => {
  it('finds the country of the prefix', () => {
    expect(countryOf('CX2AB')).toBe('Uruguay')
    expect(countryOf('PY2XYZ')).toBe('Brasil')
    expect(countryOf('XQ3SA')).toBe('Chile')
    expect(countryOf('CE3AA')).toBe('Chile')
    expect(countryOf('ZP5AA')).toBe('Paraguay')
    expect(countryOf('K1ABC')).toBe('Estados Unidos')
    expect(countryOf('BV2AA')).toBe('Taiwán')
    expect(countryOf('BA1AA')).toBe('China')
    expect(countryOf('LP1H')).toBe('Argentina')
    expect(countryOf('LA1AB')).toBe('Noruega')
  })
})

describe('entityOf', () => {
  it('gives the province of an Argentine station', () => {
    expect(entityOf('LU1EAF')).toEqual({ key: 'AR-Buenos Aires', name: 'Buenos Aires', kind: 'province' })
  })

  it('uses the suffix of a portable station', () => {
    expect(entityOf('LU2GPC/A').name).toBe('Ciudad de Buenos Aires')
    expect(entityOf('LU3HT/H').name).toBe('Córdoba')
    expect(entityOf('lu5ftm/d').name).toBe('Buenos Aires')
  })

  it('ignores a suffix G or X, and a suffix that is not a province', () => {
    expect(entityOf('LU1DAB/G').name).toBe('Buenos Aires')
    expect(entityOf('LU1DAB/X').name).toBe('Buenos Aires')
    expect(entityOf('LU1DAB/QRP').name).toBe('Buenos Aires')
  })

  it('gives the country of a different country', () => {
    expect(entityOf('CX2AB')).toEqual({ key: 'Uruguay', name: 'Uruguay', kind: 'country' })
    expect(entityOf('CX/LU1ABC').name).toBe('Uruguay')
    expect(entityOf('PY2XYZ/P').name).toBe('Brasil')
  })

  it('has no province for a foreign station in Argentina', () => {
    expect(entityOf('LU/CX1ABC').name).toBe('Argentina (provincia sin identificar)')
    expect(entityOf('LU/CX1ABC/D').name).toBe('Buenos Aires')
  })
})

describe('countEntities', () => {
  it('counts the QSOs and the participants of each entity', () => {
    const counts = countEntities([
      { callSign: 'LU1DAB', baseCallSign: 'LU1DAB', qsos: 2 },
      { callSign: 'LU1DAB/A', baseCallSign: 'LU1DAB', qsos: 1 },
      { callSign: 'LW2EZZ', baseCallSign: 'LW2EZZ', qsos: 1 },
      { callSign: 'CX2AB', baseCallSign: 'CX2AB', qsos: 1 },
      { callSign: 'QQ1ABC', baseCallSign: 'QQ1ABC', qsos: 1 },
    ])

    expect(counts.map(({ entity, qsos, participants }) => [entity.name, qsos, participants])).toEqual([
      ['Buenos Aires', 3, 2],
      ['Ciudad de Buenos Aires', 1, 1],
      ['Sin identificar', 1, 1],
      ['Uruguay', 1, 1],
    ])
    expect(entityTotal(counts)).toBe(3)
  })
})

describe('the map', () => {
  it('has a region for each province, with the keys of the entities', () => {
    const neighbours: Record<string, string> = { Chile: 'CE1AA', Bolivia: 'CP1AA', Paraguay: 'ZP1AA', Brasil: 'PY1AA', Uruguay: 'CX1AA' }
    const keys = new Set<string>([...MAP.regions.map((region) => region.key), MAP.inset.key])
    for (const key of keys) {
      if (key.startsWith('AR-')) expect(PROVINCE_NAMES.has(nameOfKey(key))).toBe(true)
      else expect(entityOf(neighbours[key]).key).toBe(key)
    }
    expect([...PROVINCE_NAMES].every((name) => keys.has(`AR-${name}`))).toBe(true)
  })
})
