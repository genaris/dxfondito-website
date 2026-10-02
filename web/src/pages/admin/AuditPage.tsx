import { useEffect, useState } from 'react'
import { actionName, detailLines, formatUtc, readAudit } from '../../audit.ts'
import type { AuditPage as AuditData } from '../../audit.ts'

type State = { kind: 'loading' } | { kind: 'ready'; data: AuditData } | { kind: 'error' }

/**
 * The record of actions (FR-AUD-4). The newest entries come first.
 */
export function AuditPage() {
  const [page, setPage] = useState(1)
  const [state, setState] = useState<State>({ kind: 'loading' })

  useEffect(() => {
    let active = true
    readAudit(page)
      .then((data) => {
        if (active) setState({ kind: 'ready', data })
      })
      .catch(() => {
        if (active) setState({ kind: 'error' })
      })
    return () => {
      active = false
    }
  }, [page])

  return (
    <>
      <h2>Registro de acciones</h2>
      {state.kind === 'loading' && <p>Cargando…</p>}
      {state.kind === 'error' && <p className="error">No se pudo leer el registro.</p>}
      {state.kind === 'ready' && state.data.entries.length === 0 && <p>El registro está vacío.</p>}
      {state.kind === 'ready' && state.data.entries.length > 0 && (
        <>
          <div className="table-scroll">
            <table className="table">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Usuario</th>
                  <th>Acción</th>
                  <th>Detalle</th>
                </tr>
              </thead>
              <tbody>
                {state.data.entries.map((entry) => (
                  <tr key={entry.id}>
                    <td className="nowrap">{formatUtc(entry.createdAt)}</td>
                    <td>{entry.user.callSign}</td>
                    <td>{actionName(entry.action)}</td>
                    <td>
                      {detailLines(entry).map((line) => (
                        <div key={line}>{line}</div>
                      ))}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {state.data.pages > 1 && (
            <nav className="actions pager" aria-label="Páginas del registro">
              <button type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>
                Más nuevas
              </button>
              <span>
                Página {state.data.page} de {state.data.pages}
              </span>
              <button type="button" disabled={page >= state.data.pages} onClick={() => setPage(page + 1)}>
                Más viejas
              </button>
            </nav>
          )}
        </>
      )}
    </>
  )
}
