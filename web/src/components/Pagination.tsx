import { clampPage, isPageSize, PAGE_SIZES, pageCount } from '../pagination.ts'
import type { PageSize } from '../pagination.ts'

/**
 * The controls below a long list (FR-PUB-4a, FR-PUB-13c). A list with no more lines than the smallest page
 * has no controls.
 */
export function Pagination({
  label,
  total,
  page,
  size,
  onPage,
  onSize,
}: {
  label: string
  total: number
  page: number
  size: PageSize
  onPage: (page: number) => void
  onSize: (size: PageSize) => void
}) {
  if (total <= PAGE_SIZES[0]) return null
  const pages = pageCount(total, size)
  const current = clampPage(page, total, size)
  const first = (current - 1) * size + 1
  const last = Math.min(current * size, total)

  return (
    <nav className="pager" aria-label={label}>
      <span className="hint">
        {first}–{last} de {total}
      </span>
      {pages > 1 && (
        <>
          <button type="button" disabled={current <= 1} onClick={() => onPage(current - 1)}>
            ‹ Anterior
          </button>
          <span>
            Página {current} de {pages}
          </span>
          <button type="button" disabled={current >= pages} onClick={() => onPage(current + 1)}>
            Siguiente ›
          </button>
        </>
      )}
      <label className="inline-field">
        Por página{' '}
        <select
          value={size}
          onChange={(event) => {
            const value = Number(event.target.value)
            if (isPageSize(value)) onSize(value)
          }}
        >
          {PAGE_SIZES.map((option) => (
            <option key={option} value={option}>
              {option}
            </option>
          ))}
        </select>
      </label>
    </nav>
  )
}
