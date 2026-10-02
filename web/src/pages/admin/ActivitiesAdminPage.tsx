import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import {
  activitySchedule,
  createActivity,
  deleteActivity,
  readActivities,
  readReferences,
  readSeasons,
  seasonOf,
  updateActivity,
} from '../../activities.ts'
import type { Activity, Reference } from '../../activities.ts'
import { changeError } from '../../messages.ts'
import { href } from '../../router.ts'
import { SeasonSelect } from '../../SeasonSelect.tsx'

type ListState = { kind: 'loading' } | { kind: 'ready'; activities: Activity[] } | { kind: 'error' }

/**
 * The administration of the activities (FR-ACT-1 to FR-ACT-8).
 */
export function ActivitiesAdminPage() {
  const [seasons, setSeasons] = useState<number[]>([])
  const [season, setSeason] = useState<number | null>(null)
  const [references, setReferences] = useState<Reference[] | null>(null)
  const [list, setList] = useState<ListState>({ kind: 'loading' })
  const [editing, setEditing] = useState<number | 'new' | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const loadSeasons = useCallback(() => {
    readSeasons()
      .then((data) => {
        setSeasons(data.seasons)
        setSeason((current) => current ?? data.current)
      })
      .catch(() => setList({ kind: 'error' }))
  }, [])

  useEffect(() => {
    loadSeasons()
    readReferences()
      .then((data) => setReferences(data.references))
      .catch(() => setList({ kind: 'error' }))
  }, [loadSeasons])

  const loadList = useCallback(() => {
    if (season === null) return
    readActivities(season)
      .then((activities) => setList({ kind: 'ready', activities }))
      .catch(() => setList({ kind: 'error' }))
  }, [season])

  useEffect(loadList, [loadList])

  function done(message: string, activity?: Activity) {
    setEditing(null)
    setNotice(message)
    // A new season can appear, and the list shows the season of the saved activity.
    loadSeasons()
    if (activity && activity.season !== season) setSeason(activity.season)
    else loadList()
  }

  function open(target: number | 'new') {
    setNotice(null)
    setError(null)
    setEditing(target)
  }

  async function remove(activity: Activity) {
    if (!window.confirm(`¿Borrar la actividad ${activity.label}?`)) return
    setNotice(null)
    setError(null)
    try {
      await deleteActivity(activity.id)
      done(`Se borró la actividad ${activity.label}.`)
    } catch (failure) {
      setError(
        changeError(failure, {
          action: 'borrar la actividad',
          conflict: `La actividad ${activity.label} tiene logs. Solo se puede borrar sin logs.`,
          notFound: 'La actividad ya no existe.',
        }),
      )
    }
  }

  if (list.kind === 'error') return <p className="error">No se pudieron leer las actividades.</p>
  if (references === null || season === null) return <p>Cargando…</p>

  return (
    <>
      <div className="title-row">
        <h2>Actividades</h2>
        <SeasonSelect seasons={seasons} value={season} onChange={setSeason} />
      </div>
      {notice && <p role="status">{notice}</p>}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {references.length === 0 ? (
        <p>
          Antes de crear una actividad, cree su referencia en <a href={href('/admin/referencias')}>Referencias</a>.
        </p>
      ) : editing === 'new' ? (
        <ActivityForm
          references={references}
          onDone={(activity) => done(`Se creó la actividad ${activity.label}.`, activity)}
          onCancel={() => setEditing(null)}
        />
      ) : (
        <p>
          <button type="button" onClick={() => open('new')}>
            Nueva actividad
          </button>
        </p>
      )}
      {list.kind === 'loading' && <p>Cargando…</p>}
      {list.kind === 'ready' && list.activities.length === 0 && <p>La temporada {season} no tiene actividades.</p>}
      {list.kind === 'ready' && list.activities.length > 0 && (
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>Referencia</th>
                <th>Nombre</th>
                <th>Fecha y horario</th>
                <th>
                  <span className="visually-hidden">Acciones</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {list.activities.map((activity) =>
                editing === activity.id ? (
                  <tr key={activity.id}>
                    <td colSpan={4}>
                      <ActivityForm
                        references={references}
                        activity={activity}
                        onDone={(changed) => done(`Se guardaron los cambios de ${changed.label}.`, changed)}
                        onCancel={() => setEditing(null)}
                      />
                    </td>
                  </tr>
                ) : (
                  <tr key={activity.id}>
                    <td className="nowrap">{activity.reference.code}</td>
                    <td>{activity.reference.name}</td>
                    <td>{activitySchedule(activity)}</td>
                    <td className="row-actions">
                      <button type="button" className="link" onClick={() => open(activity.id)}>
                        Editar
                      </button>
                      <button type="button" className="link" onClick={() => void remove(activity)}>
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

function ActivityForm({
  references,
  activity,
  onDone,
  onCancel,
}: {
  references: Reference[]
  activity?: Activity
  onDone: (activity: Activity) => void
  onCancel: () => void
}) {
  const [referenceId, setReferenceId] = useState(activity?.reference.id ?? references[0].id)
  const [startDate, setStartDate] = useState(activity?.startDate ?? '')
  const [endDate, setEndDate] = useState(activity?.endDate ?? '')
  const [startTime, setStartTime] = useState(activity?.startTime ?? '')
  const [endTime, setEndTime] = useState(activity?.endTime ?? '')
  const [description, setDescription] = useState(activity?.description ?? '')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const season = seasonOf(startDate)

  function changeStart(value: string) {
    setStartDate(value)
    // Most activities have one day. The end date follows the start date until the user changes it.
    if (endDate === '' || endDate === startDate || endDate < value) setEndDate(value)
  }

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (endDate < startDate) {
      setError('La fecha de fin no puede ser anterior a la fecha de inicio.')
      return
    }
    if (`${endDate}T${endTime}` <= `${startDate}T${startTime}`) {
      setError('El fin debe ser posterior al inicio.')
      return
    }
    setBusy(true)
    setError(null)
    const data = { referenceId, startDate, startTime, endDate, endTime, description }
    try {
      onDone(activity ? await updateActivity(activity.id, data) : await createActivity(data))
    } catch (failure) {
      const code = references.find((item) => item.id === referenceId)?.code ?? ''
      setError(
        changeError(failure, {
          action: 'guardar la actividad',
          conflict: `La referencia ${code} ya tiene una actividad que empieza el ${startDate}.`,
          notFound: 'La actividad ya no existe.',
        }),
      )
      setBusy(false)
    }
  }

  return (
    <form className="form" onSubmit={submit}>
      <h3>{activity ? `Actividad ${activity.label}` : 'Nueva actividad'}</h3>
      <label>
        Referencia
        <select value={referenceId} onChange={(event) => setReferenceId(Number(event.target.value))}>
          {references.map((reference) => (
            <option key={reference.id} value={reference.id}>
              {reference.code} · {reference.name}
            </option>
          ))}
        </select>
      </label>
      <div className="field-row">
        <label>
          Fecha de inicio (UTC)
          <input type="date" value={startDate} onChange={(event) => changeStart(event.target.value)} required />
        </label>
        <label>
          Hora de inicio (UTC)
          <input type="time" value={startTime} onChange={(event) => setStartTime(event.target.value)} required />
        </label>
      </div>
      <div className="field-row">
        <label>
          Fecha de fin (UTC)
          <input
            type="date"
            value={endDate}
            min={startDate || undefined}
            onChange={(event) => setEndDate(event.target.value)}
            required
          />
        </label>
        <label>
          Hora de fin (UTC)
          <input type="time" value={endTime} onChange={(event) => setEndTime(event.target.value)} required />
        </label>
      </div>
      <p className="hint">
        Temporada: {season ?? '—'}. Las horas van en UTC: la hora argentina más 3. Por ejemplo, de 10:00 a 15:00 en
        Argentina es de 13:00 a 18:00 UTC.
      </p>
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
          {busy ? 'Guardando…' : activity ? 'Guardar' : 'Crear la actividad'}
        </button>
        <button type="button" onClick={onCancel}>
          Cancelar
        </button>
      </div>
    </form>
  )
}
