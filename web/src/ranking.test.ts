import { describe, expect, it } from 'vitest'
import { baseCallSign, searchRanking } from './ranking.ts'
import type { RankingRow } from './ranking.ts'

function row(position: number, callSign: string): RankingRow {
  return { position, callSign, points: 1, references: ['DPS-01'], levels: [] }
}

describe('searchRanking', () => {
  const rows = [row(1, 'LU2ABC'), row(2, 'CX1A'), row(2, 'LU9ABC')]

  it('gives all lines without a search', () => {
    expect(searchRanking(rows, '  ')).toEqual(rows)
  })

  it('finds a part of the call sign in all cases and keeps the positions', () => {
    expect(searchRanking(rows, 'abc').map((item) => [item.position, item.callSign])).toEqual([
      [1, 'LU2ABC'],
      [2, 'LU9ABC'],
    ])
  })
})

describe('baseCallSign', () => {
  it('gives the longest part, as the API does', () => {
    expect(baseCallSign('lu1abc/p')).toBe('LU1ABC')
    expect(baseCallSign('CX/LU1ABC')).toBe('LU1ABC')
    expect(baseCallSign('LU1AB/LU2CD')).toBe('LU1AB')
  })
})
