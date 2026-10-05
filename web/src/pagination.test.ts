import { describe, expect, it } from 'vitest'
import { clampPage, isPageSize, pageCount, pageItems } from './pagination.ts'

const items = Array.from({ length: 60 }, (_, index) => index + 1)

describe('pageCount', () => {
  it('counts a partial last page', () => {
    expect(pageCount(60, 25)).toBe(3)
    expect(pageCount(50, 25)).toBe(2)
  })

  it('gives one page for an empty list', () => {
    expect(pageCount(0, 25)).toBe(1)
  })
})

describe('clampPage', () => {
  it('keeps the page in the range', () => {
    expect(clampPage(0, 60, 25)).toBe(1)
    expect(clampPage(2, 60, 25)).toBe(2)
    expect(clampPage(9, 60, 25)).toBe(3)
  })
})

describe('pageItems', () => {
  it('gives the items of the page', () => {
    expect(pageItems(items, 1, 25)).toEqual(items.slice(0, 25))
    expect(pageItems(items, 3, 25)).toEqual([51, 52, 53, 54, 55, 56, 57, 58, 59, 60])
  })

  it('gives the last page for a page after the end, as after a search', () => {
    expect(pageItems(items, 5, 50)).toEqual(items.slice(50))
  })
})

describe('isPageSize', () => {
  it('accepts only the sizes of the list', () => {
    expect(isPageSize(50)).toBe(true)
    expect(isPageSize(30)).toBe(false)
    expect(isPageSize('50')).toBe(false)
  })
})
