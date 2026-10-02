import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '../../api.ts'
import { formatUtc } from '../../audit.ts'
import { readRegistries, REGISTRIES, updateRegistry } from '../../registries.ts'
import type { RegistryUpdate } from '../../registries.ts'

type State = { kind: 'loading' } | { kind: 'ready'; updates: RegistryUpdate[] } | { kind: 'error' }

/**
 * The official registries of licensees of Argentina and Uruguay. The QSL cards take the name of the participant
 * from them (FR-QSL-3a). An administrator updates each registry with a button.
 */
export function LicensesPage() {
  const [state, setState] = useState<State>({ kind: 'loading' })
  const [busy, setBusy] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(() => {
    readRegistries()
      .then((updates) => setState({ kind: 'ready', updates }))
      .catch(() => setState({ kind: 'error' }))
  }, [])

  useEffect(load, [load])

  async function update(country: string, name: string) {
    setBusy(country)
    setNotice(null)
    setError(null)
    try {
      const result = await updateRegistry(country)
      setNotice(`Se actualizó el listado de ${name}: ${result.count.toLocaleString('es-AR')} licencias.`)
      load()
    } catch (failure) {
      const detail = failure instanceof ApiError && failure.status === 502 ? ` (${failure.message})` : ''
      setError(`No se pudo actualizar el listado de ${name}${detail}. El listado anterior sigue vigente.`)
    } finally {
      setBusy(null)
    }
  }

  return (
    <>
      <h2>Listados de licencias</h2>
      <p className="hint">
        La QSL muestra el nombre del participante según los listados oficiales de licencias de Argentina y Uruguay. Un
        participante de otro país, o que no figura en el listado, recibe la QSL sin nombre. El nombre del log no se
        publica.
      </p>
      {notice && <p role="status">{notice}</p>}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {state.kind === 'loading' && <p>Cargando…</p>}
      {state.kind === 'error' && <p className="error">No se pudieron leer los listados.</p>}
      {state.kind === 'ready' && (
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>País</th>
                <th>Fuente</th>
                <th className="number">Licencias</th>
                <th>Actualizado</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {REGISTRIES.map((registry) => {
                const last = state.updates.find((item) => item.country === registry.country)
                return (
                  <tr key={registry.country}>
                    <td>{registry.name}</td>
                    <td>
                      <a href={registry.page} target="_blank" rel="noopener noreferrer">
                        {registry.source}
                      </a>
                    </td>
                    <td className="number">{last ? last.count.toLocaleString('es-AR') : '—'}</td>
                    <td className="nowrap">{last ? formatUtc(last.updatedAt) : 'Nunca'}</td>
                    <td className="row-actions">
                      <button type="button" disabled={busy !== null} onClick={() => void update(registry.country, registry.name)}>
                        {busy === registry.country ? 'Actualizando…' : 'Actualizar'}
                      </button>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
      <p className="hint">
        Conviene actualizar los listados una vez por mes, o cuando un participante nuevo no tiene nombre en su QSL.
      </p>
    </>
  )
}
