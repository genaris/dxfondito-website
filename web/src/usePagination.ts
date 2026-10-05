import { useRef, useState } from 'react'
import { DEFAULT_PAGE_SIZE, isPageSize } from './pagination.ts'
import type { PageSize } from './pagination.ts'

const SIZE_KEY = 'dxfondito.pageSize'

/** The browser keeps the selected size for all lists. Without storage, the default size. */
function storedSize(): PageSize {
  try {
    const value = Number(localStorage.getItem(SIZE_KEY))
    return isPageSize(value) ? value : DEFAULT_PAGE_SIZE
  } catch {
    return DEFAULT_PAGE_SIZE
  }
}

/**
 * The page and the page size of a list in the browser (FR-PUB-4a, FR-PUB-13c).
 * Put `top` on the element before the list: a change of page from below the list shows its start.
 */
export function usePagination() {
  const [page, setPage] = useState(1)
  const [size, setSize] = useState<PageSize>(storedSize)
  const top = useRef<HTMLDivElement>(null)

  function goTo(value: number) {
    setPage(value)
    const element = top.current
    if (element && element.getBoundingClientRect().top < 0) element.scrollIntoView({ block: 'start' })
  }

  /** A new size keeps the first item of the page on the screen. */
  function changeSize(value: PageSize) {
    setPage(Math.floor(((page - 1) * size) / value) + 1)
    setSize(value)
    try {
      localStorage.setItem(SIZE_KEY, String(value))
    } catch {
      // Without storage, the size is only for this page.
    }
  }

  return { page, size, top, goTo, changeSize, reset: () => setPage(1) }
}
