/** The sizes of a page that a visitor can select (FR-PUB-4a, FR-PUB-13c). */
export const PAGE_SIZES = [25, 50, 100] as const

export type PageSize = (typeof PAGE_SIZES)[number]

export const DEFAULT_PAGE_SIZE: PageSize = 50

export function isPageSize(value: unknown): value is PageSize {
  return PAGE_SIZES.includes(value as PageSize)
}

/** The number of pages. An empty list has one page. */
export function pageCount(total: number, size: number): number {
  return Math.max(1, Math.ceil(total / size))
}

/** The page in the range 1 to the number of pages. */
export function clampPage(page: number, total: number, size: number): number {
  return Math.min(Math.max(1, page), pageCount(total, size))
}

/** The items of a page. The first page has the number 1. */
export function pageItems<T>(items: T[], page: number, size: number): T[] {
  const start = (clampPage(page, items.length, size) - 1) * size
  return items.slice(start, start + size)
}
