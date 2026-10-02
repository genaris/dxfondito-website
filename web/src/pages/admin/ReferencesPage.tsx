import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { createReference, deleteReference, readReferences, referenceCode, updateReference } from '../../activities.ts'
import type { Reference, ReferenceOverview, Series } from '../../activities.ts'
import { changeError } from '../../messages.ts'

type ListState = { kind: 'loading' } | { kind: 'ready'; data: ReferenceOverview } | { kind: 'error' }

const MAX_NUMBER = 999

/**
 * The administration of the references (FR-REF-1 to FR-REF-8).
 */
export function ReferencesPage() {
  const [list, setList] = useState<ListState>({ kind: 'loading' })
  const [editing, setEditing] = useState<number | 'new' | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(() => {
    readReferences()
      .then((data) => setList({ kind: 'ready', data }))
      .catch(() => setList({ kind: 'error' }))
  }, [])

  useEffect(load, [load])

  function done(message: string) {
    setEditing(null)
    setNotice(message)
    load()
  }

  function open(target: number | 'new') {
    setNotice(null)
    setError(null)
    setEditing(target)
  }

  async function remove(reference: Reference) {
    if (!window.confirm(`¿Borrar la referencia ${reference.code}?`)) return
    setNotice(null)
    setError(null)
    try {
      await deleteReference(reference.id)
      done(`Se borró la referencia ${reference.code}.`)
    } catch (failure) {
      setError(
        changeError(failure, {
          action: 'borrar la referencia',
          conflict: `La referencia ${reference.code} tiene actividades. Solo se puede borrar sin actividades.`,
          notFound: 'La referencia ya no existe.',
        }),
      )
    }
  }

  if (list.kind === 'loading') return <p>Cargando…</p>
  if (list.kind === 'error') return <p className="error">No se pudo leer la lista de referencias.</p>
  const { series, references } = list.data

  return (
    <>
      <h2>Referencias</h2>
      {notice && <p role="status">{notice}</p>}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {editing === 'new' ? (
        <ReferenceForm
          series={series}
          onDone={(reference) => done(`Se creó la referencia ${reference.code}.`)}
          onCancel={() => setEditing(null)}
        />
      ) : (
        <p>
          <button type="button" onClick={() => open('new')}>
            Nueva referencia
          </button>
        </p>
      )}
      {references.length === 0 ? (
        <p>No hay referencias.</p>
      ) : (
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>
                  <span className="visually-hidden">Acciones</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {references.map((reference) =>
                editing === reference.id ? (
                  <tr key={reference.id}>
                    <td colSpan={3}>
                      <ReferenceForm
                        series={series}
                        reference={reference}
                        onDone={(changed) => done(`Se guardaron los cambios de ${changed.code}.`)}
                        onCancel={() => setEditing(null)}
                      />
                    </td>
                  </tr>
                ) : (
                  <tr key={reference.id}>
                    <td className="nowrap">{reference.code}</td>
                    <td>{reference.name}</td>
                    <td className="row-actions">
                      <button type="button" className="link" onClick={() => open(reference.id)}>
                        Editar
                      </button>
                      <button type="button" className="link" onClick={() => void remove(reference)}>
                        Borrar
                      </button>
                    </td>
                  </tr>
                ),
              )}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}

function ReferenceForm({
  series,
  reference,
  onDone,
  onCancel,
}: {
  series: Series[]
  reference?: Reference
  onDone: (reference: Reference) => void
  onCancel: () => void
}) {
  const [seriesId, setSeriesId] = useState(reference?.seriesId ?? series[0]?.id ?? 0)
  const [number, setNumber] = useState(String(reference?.number ?? series[0]?.nextNumber ?? 1))
  const [name, setName] = useState(reference?.name ?? '')
  const [description, setDescription] = useState(reference?.description ?? '')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const selectedSeries = series.find((item) => item.id === seriesId)
  const code = selectedSeries && /^\d+$/.test(number) ? referenceCode(selectedSeries.code, Number(number)) : '—'

  function changeSeries(id: number) {
    setSeriesId(id)
    // A new reference gets the next free number of the series (FR-REF-3).
    if (!reference) setNumber(String(series.find((item) => item.id === id)?.nextNumber ?? 1))
  }

  async function submit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    const data = { seriesId, number: Number(number), name, description }
    try {
      onDone(reference ? await updateReference(reference.id, data) : await createReference(data))
    } catch (failure) {
      setError(
        changeError(failure, {
          action: 'guardar la referencia',
          conflict: `El código ${code} ya existe.`,
          notFound: 'La referencia ya no existe.',
        }),
      )
      setBusy(false)
    }
  }

  return (
    <form className="form" onSubmit={submit}>
      <h3>{reference ? `Referencia ${reference.code}` : 'Nueva referencia'}</h3>
      <div className="field-row">
        <label>
          Serie
          <select value={seriesId} onChange={(event) => changeSeries(Number(event.target.value))}>
            {series.map((item) => (
              <option key={item.id} value={item.id}>
                {item.code} · {item.name}
              </option>
            ))}
          </select>
        </label>
        <label>
          Número
          <input
            type="number"
            min={1}
            max={MAX_NUMBER}
            value={number}
            onChange={(event) => setNumber(event.target.value)}
            required
          />
        </label>
      </div>
      <p className="hint">Código: {code}</p>
      <label>
        Nombre
        <input value={name} onChange={(event) => setName(event.target.value)} maxLength={150} required />
      </label>
      <label>
        Descripción (opcional)
        <textarea
          value={description}
          onChange={(event) => setDescription(event.target.value)}
          maxLength={2000}
          rows={3}
        />
      </label>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <div className="actions">
        <button type="submit" disabled={busy}>
          {busy ? 'Guardando…' : reference ? 'Guardar' : 'Crear la referencia'}
        </button>
        <button type="button" onClick={onCancel}>
          Cancelar
        </button>
      </div>
    </form>
  )
}
