import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import type { FormEvent, KeyboardEvent, ReactNode } from 'react'
import { activityPeriod } from '../activities.ts'
import { ApiError } from '../api.ts'
import {
  adifUrl,
  bandOf,
  callInfo,
  clockOffset,
  clockWarning,
  isCompleteCallSign,
  MODES,
  newClientId,
  normalizeFrequency,
  problemsText,
  readLiveChanges,
  readLiveState,
  readLookup,
  syncLive,
  utcSeconds,
} from '../live.ts'
import type { ContactInput, LiveOperator, Lookup, UploadedContact } from '../live.ts'
import type { Activity } from '../activities.ts'
import {
  applyResults,
  deleteLocal,
  inputOf,
  keepMine,
  loadQueue,
  markSent,
  mergeChanges,
  nextChanges,
  pendingCount,
  readStored,
  replaceServer,
  rows,
  saveLocal,
  storeQueue,
  takeTheirs,
  writeStored,
} from '../liveQueue.ts'
import type { QueueData, Row } from '../liveQueue.ts'
import { href } from '../router.ts'
import { useSession } from '../useSession.ts'
import { KeepAwake } from './admin/useSendProgress.ts'

/** The data of the activity for the page. The page keeps the last copy, for a reload without a connection. */
interface Meta {
  activity: Activity
  operators: LiveOperator[]
  uploaded: UploadedContact[]
  savedAt: string
}

/** The settings of this browser (FR-LIVE-11): each browser has its own. */
interface Device {
  /** The operators of the buttons, in their order. */
  operatorIds: number[]
  operatorId: number
  frequency: string
  /** A mode that the user chose, such as SSB on 2 m. Null: the mode of the band. */
  mode: string | null
  manualTime: boolean
  /** The call signs that wait for a call (FR-LIVE-8). */
  waiting: string[]
  filter: 'all' | 'operator'
}

/** The new contact of the form. */
interface Draft {
  clientId: string
  callSign: string
  name: string
  /** True after the user changed the name: the help does not change it again. */
  nameTouched: boolean
  /** The time when the user started the call sign, or the time of the manual time. Null before the call sign. */
  qsoAt: string | null
  rstSent: string
  rstRcvd: string
  notes: string
}

type Connection = { kind: 'ok' } | { kind: 'offline'; since: string } | { kind: 'session' } | { kind: 'error'; message: string }

const DEFAULT_RST = '59'
const RST_CHOICES = ['59', '58', '57']

// Without changes, the page asks for the changes of the other browsers at this interval.
const IDLE_MS = 20000
// After a failed request, the wait grows to this at most.
const MAX_RETRY_MS = 30000

const metaKey = (activityId: number) => `dxf-live-meta-${activityId}`
const deviceKey = (activityId: number) => `dxf-live-device-${activityId}`
const draftKey = (activityId: number) => `dxf-live-draft-${activityId}`
// One help table for all activities: it is large. A different activity loads it again.
const LOOKUP_KEY = 'dxf-live-lookup'

/** The UTC time of the clock of this device. */
function deviceTime(): string {
  return utcSeconds(new Date())
}

function emptyDraft(): Draft {
  return { clientId: newClientId(), callSign: '', name: '', nameTouched: false, qsoAt: null, rstSent: DEFAULT_RST, rstRcvd: DEFAULT_RST, notes: '' }
}

/**
 * The live page: the contacts of an activity during the activity (FR-LIVE-1 to FR-LIVE-15).
 * Each change goes first to a queue in the browser, and then to the API. Thus a break of the connection loses
 * nothing, and the page sends the queue when the connection comes back.
 */
export function LivePage({ activityId }: { activityId: number }) {
  const { user, refresh } = useSession()
  // The ref gives the last queue to the requests. The state gives it to the render.
  const [data, setData] = useState<QueueData>(() => loadQueue(activityId))
  const queue = useRef<QueueData>(data)
  const [meta, setMeta] = useState<Meta | null>(() => readStored<Meta>(metaKey(activityId)))
  const [lookup, setLookup] = useState<Lookup | null>(() => {
    const stored = readStored<{ activityId: number; lookup: Lookup }>(LOOKUP_KEY)
    return stored?.activityId === activityId ? stored.lookup : null
  })
  const [device, setDevice] = useState<Device>(
    () =>
      readStored<Device>(deviceKey(activityId)) ?? {
        operatorIds: user ? [user.id] : [],
        operatorId: user?.id ?? 0,
        frequency: '',
        mode: null,
        manualTime: false,
        waiting: [],
        filter: 'all',
      },
  )
  const [draft, setDraft] = useState<Draft>(() => readStored<Draft>(draftKey(activityId)) ?? emptyDraft())
  const [connection, setConnection] = useState<Connection>({ kind: 'ok' })
  const [offset, setOffset] = useState<number | null>(null)
  const [loadFailed, setLoadFailed] = useState(false)
  const [editing, setEditing] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const callInput = useRef<HTMLInputElement>(null)
  const waitingInput = useRef<HTMLInputElement>(null)
  const kick = useRef<() => void>(() => undefined)
  const syncing = useRef(false)
  const failures = useRef(0)
  // The first request of each opening reads all data again: the operators, the uploaded logs and all contacts.
  const loaded = useRef(false)

  const update = useCallback(
    (change: (data: QueueData) => QueueData) => {
      queue.current = change(queue.current)
      storeQueue(activityId, queue.current)
      setData(queue.current)
    },
    [activityId],
  )

  useEffect(() => {
    writeStored(deviceKey(activityId), device)
  }, [activityId, device])

  useEffect(() => {
    writeStored(draftKey(activityId), draft)
  }, [activityId, draft])

  const loadAll = useCallback(async () => {
    const sentAt = Date.now()
    const state = await readLiveState(activityId)
    setOffset(clockOffset(state.serverTime, sentAt, Date.now()))
    const next: Meta = { activity: state.activity, operators: state.operators, uploaded: state.uploaded, savedAt: state.serverTime }
    writeStored(metaKey(activityId), next)
    setMeta(next)
    update((data) => replaceServer(data, state.contacts, state.cursor))
  }, [activityId, update])

  /** One request: the queue, or the changes of the other browsers. One request at a time. */
  const syncNow = useCallback(async () => {
    if (syncing.current) return
    syncing.current = true
    try {
      if (!loaded.current || queue.current.cursor === null) {
        await loadAll()
        loaded.current = true
      } else {
        const { changes, seqs } = nextChanges(queue.current)
        const since = queue.current.cursor
        const sentAt = Date.now()
        if (changes.length > 0) {
          update((data) => markSent(data, seqs))
          const answer = await syncLive(activityId, changes, since)
          setOffset(clockOffset(answer.serverTime, sentAt, Date.now()))
          update((data) => mergeChanges(applyResults(data, seqs, answer.results), answer))
        } else {
          const answer = await readLiveChanges(activityId, since)
          setOffset(clockOffset(answer.serverTime, sentAt, Date.now()))
          update((data) => mergeChanges(data, answer))
        }
      }
      failures.current = 0
      setConnection({ kind: 'ok' })
      setLoadFailed(false)
    } catch (error) {
      failures.current++
      if (error instanceof ApiError && (error.status === 401 || error.status === 403)) {
        // The session ended, or the page has no token after a reload without a connection. A new reading of the
        // session gives the token. Without a session, the sign-in page comes, and the queue stays.
        setConnection({ kind: 'session' })
        await refresh().catch(() => undefined)
      } else if (error instanceof ApiError) {
        setConnection({ kind: 'error', message: error.status === 404 ? 'La actividad ya no existe.' : 'El sitio dio un error.' })
        if (!loaded.current) setLoadFailed(true)
      } else {
        setConnection((current) => (current.kind === 'offline' ? current : { kind: 'offline', since: new Date().toISOString() }))
      }
    } finally {
      syncing.current = false
    }
  }, [activityId, loadAll, refresh, update])

  // The loop of requests: soon after a change, slower without changes, and with a growing wait after a failure.
  useEffect(() => {
    let timer: ReturnType<typeof setTimeout> | undefined
    let stopped = false
    const run = async () => {
      clearTimeout(timer)
      await syncNow()
      if (stopped) return
      const waiting = nextChanges(queue.current).changes.length > 0
      const delay = failures.current > 0 ? Math.min(MAX_RETRY_MS, 5000 * failures.current) : waiting ? 1000 : IDLE_MS
      timer = setTimeout(() => void run(), delay)
    }
    kick.current = () => void run()
    const onVisible = () => {
      if (document.visibilityState === 'visible') void run()
    }
    window.addEventListener('online', kick.current)
    document.addEventListener('visibilitychange', onVisible)
    void run()
    return () => {
      stopped = true
      clearTimeout(timer)
      window.removeEventListener('online', kick.current)
      document.removeEventListener('visibilitychange', onVisible)
    }
  }, [syncNow])

  // The help table: once for each opening of the page, and the last copy without a connection.
  useEffect(() => {
    readLookup(activityId)
      .then((table) => {
        setLookup(table)
        writeStored(LOOKUP_KEY, { activityId, lookup: table })
      })
      .catch(() => undefined)
  }, [activityId])

  // A closed tab with changes in the queue: the browser asks first. The queue stays in any case.
  useEffect(() => {
    const warn = (event: BeforeUnloadEvent) => {
      if (pendingCount(queue.current) > 0) event.preventDefault()
    }
    window.addEventListener('beforeunload', warn)
    return () => window.removeEventListener('beforeunload', warn)
  }, [])

  // A phone that turns off its screen stops the page.
  useEffect(() => {
    const awake = new KeepAwake()
    return () => awake.release()
  }, [])

  const operators = useMemo(() => meta?.operators ?? [], [meta])
  const list = rows(data, operators)
  const sessionOperators = device.operatorIds
    .map((id) => operators.find((operator) => operator.id === id))
    .filter((operator): operator is LiveOperator => operator !== undefined)
  const current = operators.find((operator) => operator.id === device.operatorId) ?? sessionOperators[0] ?? null
  const frequency = normalizeFrequency(device.frequency)
  const band = frequency ? bandOf(frequency) : { band: null, mode: null }
  const mode = device.mode ?? band.mode

  // The contacts for the help: of this page, and of the uploaded logs.
  const helpContacts = useCallback(
    (except: string | null) => [
      ...list.filter((row) => row.clientId !== except).map(({ callSign, qsoAt, station, band }) => ({ callSign, qsoAt, station, band })),
      ...(meta?.uploaded ?? []),
    ],
    [list, meta],
  )

  if (!user) return null
  if (meta === null) {
    return loadFailed || connection.kind === 'offline' ? (
      <p className="error">No se pudo leer la actividad. La página necesita la conexión la primera vez.</p>
    ) : (
      <p>Cargando…</p>
    )
  }

  const { activity } = meta

  function changeDevice(change: Partial<Device>) {
    setDevice((value) => ({ ...value, ...change }))
  }

  function selectOperator(operatorId: number) {
    changeDevice({
      operatorId,
      operatorIds: device.operatorIds.includes(operatorId) ? device.operatorIds : [...device.operatorIds, operatorId],
    })
  }

  function changeCallSign(text: string) {
    const callSign = text.toUpperCase().replace(/\s+/g, '')
    setFormError(null)
    setDraft((value) => {
      // The automatic time of the contact is the time of the first character (FR-LIVE-6). A new call sign
      // after an empty field gives a new time.
      const automatic = callSign === '' ? null : (value.qsoAt ?? deviceTime())
      return { ...value, callSign, qsoAt: device.manualTime ? value.qsoAt : automatic }
    })
  }

  function save(event: FormEvent) {
    event.preventDefault()
    if (current === null) return setFormError('Elija el operador.')
    if (!/^[A-Z0-9/]{3,20}$/.test(draft.callSign)) return setFormError('El indicativo no es válido.')
    if (frequency === null) return setFormError('Escriba la frecuencia en MHz, por ejemplo 7.130.')
    if (mode === null) return setFormError('La frecuencia está fuera de las bandas del programa. Elija SSB o FM.')
    const qsoAt = draft.qsoAt ?? deviceTime()
    const input: ContactInput = {
      operatorId: current.id,
      callSign: draft.callSign,
      name: name.trim(),
      qsoAt,
      frequency,
      mode,
      rstSent: draft.rstSent.trim(),
      rstRcvd: draft.rstRcvd.trim(),
      notes: draft.notes.trim(),
    }
    update((data) => saveLocal(data, draft.clientId, input))
    setDraft({ ...emptyDraft(), qsoAt: device.manualTime ? qsoAt : null })
    setFormError(null)
    kick.current()
    callInput.current?.focus()
  }

  function clearDraft() {
    setDraft((value) => ({ ...emptyDraft(), qsoAt: device.manualTime ? value.qsoAt : null }))
    setFormError(null)
  }

  function addWaiting(text: string) {
    const callSign = text.toUpperCase().replace(/\s+/g, '')
    if (callSign === '') return
    changeDevice({ waiting: [...device.waiting, callSign] })
  }

  /** A waiting call sign goes to the form. A call sign of the form goes back to the waiting list. */
  function callWaiting(index: number) {
    const callSign = device.waiting[index]
    const rest = device.waiting.filter((_, position) => position !== index)
    changeDevice({ waiting: draft.callSign ? [...rest, draft.callSign] : rest })
    setDraft((value) => ({ ...emptyDraft(), qsoAt: device.manualTime ? value.qsoAt : null }))
    changeCallSign(callSign)
    callInput.current?.focus()
  }

  function onKeyDown(event: KeyboardEvent) {
    if (!event.altKey) return
    // The code of the key: Alt and a digit give a different character on a Mac.
    const digit = /^Digit([1-9])$/.exec(event.code)
    if (digit && sessionOperators[Number(digit[1]) - 1]) {
      event.preventDefault()
      selectOperator(sessionOperators[Number(digit[1]) - 1].id)
    } else if (event.code === 'KeyE') {
      event.preventDefault()
      waitingInput.current?.focus()
    }
  }

  const info = isCompleteCallSign(draft.callSign) ? callInfo(draft.callSign, lookup, helpContacts(null)) : null
  // The name of the help until the user changes it. The help table can come after the call sign.
  const name = draft.nameTouched ? draft.name : (info?.name ?? '')
  const shown = device.filter === 'operator' && current ? list.filter((row) => row.operatorId === current.id) : list
  const unsent = pendingCount(data)
  const errors = list.filter((row) => row.state === 'error' || row.state === 'conflict').length
  const warning = offset === null ? null : clockWarning(offset)
  const period = activityPeriod(activity)
  const canExport = sessionOperators.filter((operator) => user.role === 'administrator' || operator.id === user.id)

  return (
    <div className="live" onKeyDown={onKeyDown}>
      <p>
        <a href={href(`/actividad/${activity.id}`)}>← {activity.reference.code} · {activity.reference.name}</a>
      </p>
      <div className="live-head">
        <h2>Carga en vivo</h2>
        <ConnectionStatus connection={connection} unsent={unsent} errors={errors} />
      </div>
      {warning && (
        <p className="live-alert" role="alert">
          {warning}
        </p>
      )}
      {connection.kind === 'offline' && meta.savedAt && (
        <p className="hint">Sin conexión: la lista y las ayudas son las de {meta.savedAt.slice(11, 16)} UTC, más lo que se cargó acá.</p>
      )}

      <section className="live-bar" aria-label="Datos fijos">
        <div className="live-operators" role="group" aria-label="Operador">
          {sessionOperators.map((operator, index) => (
            <button
              key={operator.id}
              type="button"
              className={operator.id === current?.id ? 'chip active' : 'chip'}
              aria-pressed={operator.id === current?.id}
              onClick={() => selectOperator(operator.id)}
              title={index < 9 ? `Alt+${index + 1}` : undefined}
            >
              {operator.station}
            </button>
          ))}
          <select
            aria-label="Agregar un operador"
            value=""
            onChange={(event) => event.target.value && selectOperator(Number(event.target.value))}
          >
            <option value="">+ Operador</option>
            {operators
              .filter((operator) => !device.operatorIds.includes(operator.id))
              .map((operator) => (
                <option key={operator.id} value={operator.id}>
                  {operator.station} · {operator.name}
                </option>
              ))}
          </select>
          {sessionOperators.length > 1 && current && (
            <button
              type="button"
              className="link"
              onClick={() => {
                const rest = device.operatorIds.filter((id) => id !== current.id)
                changeDevice({ operatorIds: rest, operatorId: rest[0] })
              }}
            >
              Quitar {current.station}
            </button>
          )}
        </div>
        <div className="live-radio">
          <label>
            Frecuencia (MHz)
            <input
              value={device.frequency}
              inputMode="decimal"
              placeholder="7.130"
              onChange={(event) => changeDevice({ frequency: event.target.value, mode: null })}
            />
          </label>
          <span className="live-band">
            {frequency === null ? (
              device.frequency && <span className="error">Frecuencia incorrecta</span>
            ) : (
              <>
                {band.band ?? 'fuera de banda'} ·{' '}
                {MODES.map((choice) => (
                  <button
                    key={choice}
                    type="button"
                    className={mode === choice ? 'chip active' : 'chip'}
                    aria-pressed={mode === choice}
                    onClick={() => changeDevice({ mode: choice === band.mode ? null : choice })}
                  >
                    {choice}
                  </button>
                ))}
                {band.mode === null && <span className="warning-text"> Fuera de las bandas del programa</span>}
              </>
            )}
          </span>
          <label className="checkbox">
            <input
              type="checkbox"
              checked={device.manualTime}
              onChange={(event) => {
                changeDevice({ manualTime: event.target.checked })
                setDraft((value) => ({ ...value, qsoAt: event.target.checked ? (value.qsoAt ?? `${activity.startDate}T${activity.startTime ?? '00:00'}:00Z`) : null }))
              }}
            />
            Hora manual (carga después del evento)
          </label>
        </div>
      </section>

      <form className="live-form" onSubmit={save} aria-label="Nuevo QSO">
        <div className="live-call">
          <label>
            Indicativo
            <input
              ref={callInput}
              className="live-call-input"
              value={draft.callSign}
              onChange={(event) => changeCallSign(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Escape') clearDraft()
              }}
              autoComplete="off"
              autoCapitalize="characters"
              spellCheck={false}
              autoFocus
            />
          </label>
          <TimeField
            value={draft.qsoAt}
            manual={device.manualTime}
            onChange={(qsoAt) => setDraft((value) => ({ ...value, qsoAt }))}
          />
          {/* Next to the call sign: on a phone, the keyboard on the screen makes a scroll difficult (FR-LIVE-8). */}
          <div className="live-waiting">
            <label>
              En espera
              <input
                ref={waitingInput}
                placeholder="Anotar indicativo"
                title="Alt+E"
                autoCapitalize="characters"
                autoComplete="off"
                spellCheck={false}
                enterKeyHint="enter"
                onKeyDown={(event) => {
                  if (event.key !== 'Enter') return
                  // Enter adds the call sign. It does not save the contact of the form.
                  event.preventDefault()
                  addWaiting(event.currentTarget.value)
                  event.currentTarget.value = ''
                }}
              />
            </label>
            {device.waiting.length > 0 && (
              <div className="live-chips">
                {device.waiting.map((callSign, index) => (
                  <span key={`${callSign}-${index}`} className="waiting-chip">
                    <button type="button" className="link" onClick={() => callWaiting(index)} title="Pasar al formulario">
                      {callSign}
                    </button>
                    <button
                      type="button"
                      className="link"
                      aria-label={`Quitar ${callSign}`}
                      onClick={() => changeDevice({ waiting: device.waiting.filter((_, position) => position !== index) })}
                    >
                      ✕
                    </button>
                  </span>
                ))}
              </div>
            )}
          </div>
        </div>
        <CallHelp info={info} reference={lookup?.reference ?? activity.reference.code} station={current?.station ?? null} hasLookup={lookup !== null} />
        <label>
          Nombre
          <input
            value={name}
            onChange={(event) => setDraft((value) => ({ ...value, name: event.target.value, nameTouched: true }))}
            autoComplete="off"
          />
        </label>
        <div className="live-rst">
          <RstPicker label="RST enviado" value={draft.rstSent} onChange={(rstSent) => setDraft((value) => ({ ...value, rstSent }))} />
          <RstPicker label="RST recibido" value={draft.rstRcvd} onChange={(rstRcvd) => setDraft((value) => ({ ...value, rstRcvd }))} />
        </div>
        <label>
          Observaciones <span className="hint">(se exportan en el ADIF)</span>
          <input value={draft.notes} maxLength={500} onChange={(event) => setDraft((value) => ({ ...value, notes: event.target.value }))} />
        </label>
        {formError && (
          <p className="error" role="alert">
            {formError}
          </p>
        )}
        <div className="actions">
          <button type="submit" disabled={draft.callSign === ''}>
            Guardar{current ? ` para ${current.station}` : ''}
          </button>
          <button type="button" onClick={clearDraft}>
            Limpiar (Esc)
          </button>
        </div>
      </form>

      <section>
        <div className="live-head">
          <h3>QSOs · {list.length}</h3>
          {current && (
            <div className="actions">
              <button
                type="button"
                className={device.filter === 'all' ? 'chip active' : 'chip'}
                onClick={() => changeDevice({ filter: 'all' })}
              >
                Todos
              </button>
              <button
                type="button"
                className={device.filter === 'operator' ? 'chip active' : 'chip'}
                onClick={() => changeDevice({ filter: 'operator' })}
              >
                Solo {current.station}
              </button>
            </div>
          )}
        </div>
        {shown.length === 0 ? (
          <p>Todavía no hay QSOs.</p>
        ) : (
          <div className="table-scroll">
            <table className="table live-table">
              <thead>
                <tr>
                  <th>Hora (UTC)</th>
                  <th>Indicativo</th>
                  <th>Nombre</th>
                  <th>RST</th>
                  <th>Frecuencia</th>
                  <th>Operador</th>
                  <th>Observaciones</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                {shown.map((row) =>
                  editing === row.clientId ? (
                    <tr key={row.clientId}>
                      <td colSpan={8}>
                        <RowEditor
                          row={row}
                          operators={operators}
                          onSave={(input) => {
                            update((data) => saveLocal(data, row.clientId, input))
                            setEditing(null)
                            kick.current()
                          }}
                          onDelete={() => {
                            if (!window.confirm(`¿Borrar el QSO con ${row.callSign} de las ${row.qsoAt.slice(11, 16)}?`)) return
                            update((data) => deleteLocal(data, row.clientId))
                            setEditing(null)
                            kick.current()
                          }}
                          onCancel={() => setEditing(null)}
                        />
                      </td>
                    </tr>
                  ) : (
                    <tr key={row.clientId} className="live-row" onClick={() => setEditing(row.clientId)}>
                      <td className="nowrap">
                        {row.qsoAt.slice(0, 10) === activity.startDate ? '' : `${row.qsoAt.slice(0, 10)} `}
                        {row.qsoAt.slice(11, 19)}
                      </td>
                      <td className="nowrap">
                        <button type="button" className="link live-edit" title="Corregir">
                          {row.callSign}
                        </button>
                      </td>
                      <td>{row.name}</td>
                      <td className="nowrap">
                        {row.rstSent ?? '–'}/{row.rstRcvd ?? '–'}
                      </td>
                      <td className="nowrap">
                        {row.frequency} {row.mode}
                      </td>
                      <td className="nowrap">{row.station}</td>
                      <td>{row.notes}</td>
                      <td>
                        <RowState row={row} outOfHours={row.qsoAt.slice(0, 16) < period.start || row.qsoAt.slice(0, 16) > period.end}>
                          {row.state === 'conflict' && (
                            <ConflictChoice
                              row={row}
                              onKeep={() => {
                                update((data) => keepMine(data, row.clientId))
                                kick.current()
                              }}
                              onTake={() => update((data) => takeTheirs(data, row.clientId))}
                            />
                          )}
                        </RowState>
                      </td>
                    </tr>
                  ),
                )}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {canExport.length > 0 && (
        <section>
          <h3>ADIF para QRZ y LdA</h3>
          <p className="hint">Todos los QSOs del operador en la actividad: los cargados acá y los de los logs subidos.</p>
          <p className="actions">
            {canExport.map((operator) => (
              <a key={operator.id} className="chip" href={adifUrl(activity.id, operator.id)}>
                {operator.station}
              </a>
            ))}
          </p>
        </section>
      )}
    </div>
  )
}

function ConnectionStatus({ connection, unsent, errors }: { connection: Connection; unsent: number; errors: number }) {
  const queue = `${unsent} sin enviar${errors > 0 ? ` · ${errors} para revisar` : ''}`
  if (connection.kind === 'offline') {
    return (
      <p className="live-status live-status-offline" role="status">
        ○ Sin conexión desde {connection.since.slice(11, 16)} UTC · {queue}
      </p>
    )
  }
  if (connection.kind === 'session') {
    return (
      <p className="live-status live-status-offline" role="status">
        La sesión terminó. Ingrese de nuevo: los QSOs sin enviar quedan guardados · {queue}
      </p>
    )
  }
  if (connection.kind === 'error') {
    return (
      <p className="live-status live-status-offline" role="status">
        {connection.message} Se reintenta solo · {queue}
      </p>
    )
  }
  return (
    <p className={unsent > 0 ? 'live-status live-status-sending' : 'live-status'} role="status">
      ● Conectado · {queue}
    </p>
  )
}

function CallHelp({
  info,
  reference,
  station,
  hasLookup,
}: {
  info: ReturnType<typeof callInfo> | null
  reference: string
  station: string | null
  hasLookup: boolean
}) {
  if (info === null) return <p className="live-help hint">{hasLookup ? ' ' : 'Las ayudas se cargan con la conexión.'}</p>
  const repeated = info.previous.find((contact) => contact.station === station)
  return (
    <p className="live-help" aria-live="polite">
      {info.name && <strong>{info.name}</strong>}
      {info.name && ' · '}
      {info.entity.name}
      {info.points !== null && (
        <>
          {' · '}
          {info.points === 1 ? '1 punto' : `${info.points} puntos`}
          {' · '}
          {info.newPoint ? <span className="live-new-point">suma 1 punto</span> : <span>ya tiene {reference}</span>}
        </>
      )}
      {info.previous.length > 0 && (
        <span className={repeated ? 'warning-text' : undefined}>
          {' · '}
          {repeated ? 'Duplicado: ' : 'Ya trabajado: '}
          {info.previous
            .slice(0, 3)
            .map((contact) => `${contact.qsoAt.slice(11, 16)} con ${contact.station}${contact.band ? ` en ${contact.band}` : ''}`)
            .join(', ')}
          {info.previous.length > 3 && ` y ${info.previous.length - 3} más`}
        </span>
      )}
    </p>
  )
}

/** The time of the new contact: the automatic time, which the user can correct, or the manual date and time. */
function TimeField({ value, manual, onChange }: { value: string | null; manual: boolean; onChange: (qsoAt: string | null) => void }) {
  const date = value?.slice(0, 10) ?? ''
  const time = value?.slice(11, 19) ?? ''
  const set = (newDate: string, newTime: string) => {
    if (/^\d{4}-\d{2}-\d{2}$/.test(newDate) && /^\d{2}:\d{2}:\d{2}$/.test(newTime)) onChange(`${newDate}T${newTime}Z`)
  }
  if (!manual && value === null) {
    return (
      <p className="live-time hint">
        Hora (UTC)
        <br />
        al escribir el indicativo
      </p>
    )
  }
  return (
    <div className="live-time">
      {manual && (
        <label>
          Fecha (UTC)
          <input type="date" value={date} onChange={(event) => set(event.target.value, time || '00:00:00')} />
        </label>
      )}
      <label>
        Hora (UTC)
        <TimeInput value={time} onChange={(newTime) => set(date, newTime)} />
      </label>
    </div>
  )
}

/**
 * A time as HH:MM:SS or HH:MM, always with 24 hours: an input of the type "time" shows AM and PM in some languages.
 * The value changes only with a complete time.
 */
function TimeInput({ value, onChange }: { value: string; onChange: (time: string) => void }) {
  const [text, setText] = useState(value)
  const [shown, setShown] = useState(value)
  // A new value from outside, such as a new automatic time, replaces the text.
  if (value !== shown) {
    setShown(value)
    setText(value)
  }
  return (
    <input
      className="live-time-input"
      value={text}
      inputMode="numeric"
      placeholder="HH:MM:SS"
      maxLength={8}
      onChange={(event) => {
        setText(event.target.value)
        const match = /^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/.exec(event.target.value)
        if (match) onChange(`${match[1]}:${match[2]}:${match[3] ?? '00'}`)
      }}
    />
  )
}

function RstPicker({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  return (
    <fieldset className="rst-picker">
      <legend>{label}</legend>
      {RST_CHOICES.map((choice) => (
        <button
          key={choice}
          type="button"
          className={value === choice ? 'chip active' : 'chip'}
          aria-pressed={value === choice}
          onClick={() => onChange(choice)}
        >
          {choice}
        </button>
      ))}
      <input
        aria-label={`${label}, otro valor`}
        value={RST_CHOICES.includes(value) ? '' : value}
        placeholder="otro"
        inputMode="numeric"
        maxLength={3}
        onChange={(event) => onChange(event.target.value.replace(/\D/g, ''))}
      />
    </fieldset>
  )
}

function RowState({ row, outOfHours, children }: { row: Row; outOfHours: boolean; children?: ReactNode }) {
  return (
    <>
      {row.state === 'sent' && <span className="status status-sent">✓</span>}
      {row.state === 'pending' && <span className="status status-pending">Sin enviar</span>}
      {row.state === 'error' && (
        <span className="status status-failed" title={row.pending?.error ?? undefined}>
          {row.pending && row.pending.problems.length > 0 ? `Corregir: ${problemsText(row.pending.problems)}` : 'Error: corregir'}
        </span>
      )}
      {row.state === 'conflict' && <span className="status status-changed">Conflicto</span>}
      {outOfHours && <span className="warning-text"> Fuera del horario</span>}
      {children}
    </>
  )
}

/**
 * A different browser changed or deleted the contact first (FR-LIVE-11). The user keeps one of the two versions.
 */
function ConflictChoice({ row, onKeep, onTake }: { row: Row; onKeep: () => void; onTake: () => void }) {
  const server = row.pending?.conflict?.server ?? null
  return (
    <div className="live-conflict" onClick={(event) => event.stopPropagation()}>
      <p className="hint">
        {server
          ? `El otro equipo dejó: ${server.callSign} ${server.qsoAt.slice(11, 19)} ${server.rstSent ?? '–'}/${server.rstRcvd ?? '–'} ${server.frequency} ${server.station}${server.notes ? ` "${server.notes}"` : ''}.`
          : 'El otro equipo lo borró.'}
      </p>
      <div className="actions">
        <button type="button" onClick={onKeep}>
          {server ? 'Dejar la mía' : 'Volver a guardarlo'}
        </button>
        <button type="button" onClick={onTake}>
          {server ? 'Usar la otra' : 'Dejarlo borrado'}
        </button>
      </div>
    </div>
  )
}

/** The correction of a contact of the list (FR-LIVE-9), with all its values. */
function RowEditor({
  row,
  operators,
  onSave,
  onDelete,
  onCancel,
}: {
  row: Row
  operators: LiveOperator[]
  onSave: (input: ContactInput) => void
  onDelete: () => void
  onCancel: () => void
}) {
  const [value, setValue] = useState<ContactInput>(() => inputOf(row))
  const [error, setError] = useState<string | null>(null)
  const set = (change: Partial<ContactInput>) => setValue((current) => ({ ...current, ...change }))

  function submit(event: FormEvent) {
    event.preventDefault()
    const callSign = value.callSign.toUpperCase().replace(/\s+/g, '')
    const frequency = normalizeFrequency(value.frequency)
    if (!/^[A-Z0-9/]{3,20}$/.test(callSign)) return setError('El indicativo no es válido.')
    if (frequency === null) return setError('Escriba la frecuencia en MHz, por ejemplo 7.130.')
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(value.qsoAt)) return setError('La fecha o la hora no es válida.')
    onSave({ ...value, callSign, frequency, name: value.name.trim(), notes: value.notes.trim() })
  }

  return (
    <form className="live-editor" onSubmit={submit} onKeyDown={(event) => event.key === 'Escape' && onCancel()}>
      <div className="field-row">
        <label>
          Indicativo
          <input value={value.callSign} onChange={(event) => set({ callSign: event.target.value })} autoFocus autoCapitalize="characters" />
        </label>
        <label>
          Nombre
          <input value={value.name} onChange={(event) => set({ name: event.target.value })} />
        </label>
        <label>
          Operador
          <select value={value.operatorId} onChange={(event) => set({ operatorId: Number(event.target.value) })}>
            {operators.map((operator) => (
              <option key={operator.id} value={operator.id}>
                {operator.station}
              </option>
            ))}
          </select>
        </label>
      </div>
      <div className="field-row">
        <label>
          Fecha (UTC)
          <input
            type="date"
            value={value.qsoAt.slice(0, 10)}
            onChange={(event) => set({ qsoAt: `${event.target.value}T${value.qsoAt.slice(11, 19)}Z` })}
          />
        </label>
        <label>
          Hora (UTC)
          <TimeInput value={value.qsoAt.slice(11, 19)} onChange={(time) => set({ qsoAt: `${value.qsoAt.slice(0, 10)}T${time}Z` })} />
        </label>
        <label>
          Frecuencia (MHz)
          <input value={value.frequency} inputMode="decimal" onChange={(event) => set({ frequency: event.target.value })} />
        </label>
        <label>
          Modo
          <select value={value.mode} onChange={(event) => set({ mode: event.target.value })}>
            {MODES.map((choice) => (
              <option key={choice}>{choice}</option>
            ))}
          </select>
        </label>
      </div>
      <div className="live-rst">
        <RstPicker label="RST enviado" value={value.rstSent} onChange={(rstSent) => set({ rstSent })} />
        <RstPicker label="RST recibido" value={value.rstRcvd} onChange={(rstRcvd) => set({ rstRcvd })} />
      </div>
      <label>
        Observaciones <span className="hint">(se exportan en el ADIF)</span>
        <input value={value.notes} maxLength={500} onChange={(event) => set({ notes: event.target.value })} />
      </label>
      {row.pending?.error && (
        <p className="error">
          El sitio no aceptó este QSO
          {row.pending.problems.length > 0 ? `: ${problemsText(row.pending.problems)}` : ''}.
        </p>
      )}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <div className="actions">
        <button type="submit">Guardar la corrección</button>
        <button type="button" onClick={onCancel}>
          Cancelar
        </button>
        <button type="button" onClick={onDelete}>
          Borrar el QSO
        </button>
      </div>
    </form>
  )
}
