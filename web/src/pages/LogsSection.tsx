import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { listAccounts } from '../accounts.ts'
import type { Account } from '../accounts.ts'
import { formatUtc } from '../audit.ts'
import { adifUrl } from '../live.ts'
import {
  deleteLog,
  invalidRecordText,
  isAdifFileName,
  logFileUrl,
  MAX_FILE_BYTES,
  previewLog,
  readLogs,
  saveLog,
  warningTexts,
} from '../logs.ts'
import type { Log, Summary } from '../logs.ts'
import { changeError, uploadError } from '../messages.ts'
import { href } from '../router.ts'
import type { User } from '../useSession.ts'

type ListState = { kind: 'loading' } | { kind: 'ready'; logs: Log[] } | { kind: 'error' }

/**
 * The logs of an activity, for the signed-in users (FR-LOG-19 to FR-LOG-24).
 */
export function LogsSection({ activityId, user, onChange }: { activityId: number; user: User; onChange: () => void }) {
  const [list, setList] = useState<ListState>({ kind: 'loading' })
  const [uploading, setUploading] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(() => {
    readLogs(activityId)
      .then((logs) => setList({ kind: 'ready', logs }))
      .catch(() => setList({ kind: 'error' }))
  }, [activityId])

  useEffect(load, [load])

  function changed(message: string) {
    setUploading(false)
    setNotice(message)
    load()
    onChange()
  }

  async function remove(log: Log) {
    // FR-LOG-24: a confirmation before the deletion.
    const question =
      log.source === 'live'
        ? `¿Borrar el log en vivo de ${log.operator.callSign}? Se borran sus ${log.contactCount} QSOs cargados en la página en vivo.`
        : `¿Borrar el log ${log.fileName} de ${log.operator.callSign}? Se borran también sus ${log.contactCount} contactos.`
    if (!window.confirm(question)) return
    setNotice(null)
    setError(null)
    try {
      await deleteLog(log.id)
      changed(`Se borró el log ${log.fileName}.`)
    } catch (failure) {
      setError(
        changeError(failure, { action: 'borrar el log', conflict: 'No se pudo borrar el log.', notFound: 'El log ya no existe.' }),
      )
    }
  }

  return (
    <section>
      <h3>Logs</h3>
      {notice && <p role="status">{notice}</p>}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {uploading ? (
        <UploadForm
          activityId={activityId}
          user={user}
          onDone={(log) => changed(`Se guardó el log ${log.fileName} con ${log.contactCount} contactos.`)}
          onCancel={() => setUploading(false)}
        />
      ) : (
        <p>
          <button
            type="button"
            onClick={() => {
              setNotice(null)
              setUploading(true)
            }}
          >
            Subir un log
          </button>
        </p>
      )}
      {list.kind === 'loading' && <p>Cargando…</p>}
      {list.kind === 'error' && <p className="error">No se pudo leer la lista de logs.</p>}
      {list.kind === 'ready' && list.logs.length === 0 && <p>La actividad no tiene logs.</p>}
      {list.kind === 'ready' && list.logs.length > 0 && (
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>Archivo</th>
                <th>Operador</th>
                <th>Subido</th>
                <th>Contactos</th>
                <th>
                  <span className="visually-hidden">Acciones</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {list.logs.map((log) => (
                <tr key={log.id}>
                  <td>
                    <a href={href(`/log/${log.id}`)}>{log.source === 'live' ? 'En vivo' : log.fileName}</a>
                  </td>
                  <td>{log.operator.callSign}</td>
                  <td className="nowrap">{formatUtc(log.uploadedAt)}</td>
                  <td>
                    {log.contactCount}
                    {log.skippedCount > 0 && <span className="hint"> ({log.skippedCount} omitidos: ya estaban en vivo)</span>}
                  </td>
                  <td className="row-actions">
                    {log.canManage && (
                      <>
                        <a href={logFileUrl(log.id)} download={log.fileName}>
                          Descargar
                        </a>
                        <button type="button" className="link" onClick={() => void remove(log)}>
                          Borrar
                        </button>
                      </>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {list.kind === 'ready' && <AdifLinks activityId={activityId} logs={list.logs} />}
    </section>
  )
}

/**
 * The ADIF file of each operator, for QRZ.com and LdA: all contacts of the operator in the activity, of the live
 * log and of the uploaded logs (FR-LIVE-13). For the operator and the administrators.
 */
function AdifLinks({ activityId, logs }: { activityId: number; logs: Log[] }) {
  const operators = new Map(logs.filter((log) => log.canManage).map((log) => [log.operator.id, log.operator.callSign]))
  if (operators.size === 0) return null
  return (
    <p className="actions">
      <span>ADIF para QRZ y LdA:</span>
      {[...operators].map(([id, callSign]) => (
        <a key={id} className="chip" href={adifUrl(activityId, id)}>
          {callSign}
        </a>
      ))}
    </p>
  )
}

/**
 * The upload in two steps: the summary, then the save operation or the cancellation (FR-LOG-10 to FR-LOG-16).
 */
function UploadForm({
  activityId,
  user,
  onDone,
  onCancel,
}: {
  activityId: number
  user: User
  onDone: (log: Log) => void
  onCancel: () => void
}) {
  const isAdministrator = user.role === 'administrator'
  const [accounts, setAccounts] = useState<Account[]>([])
  const [operatorId, setOperatorId] = useState(user.id)
  const [file, setFile] = useState<File | null>(null)
  const [summary, setSummary] = useState<Summary | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  // FR-LOG-4: an administrator selects the operator of the log.
  useEffect(() => {
    if (!isAdministrator) return
    listAccounts()
      .then((all) => setAccounts(all.filter((account) => account.active)))
      .catch(() => setAccounts([]))
  }, [isAdministrator])

  function selectFile(selected: File | null) {
    setFile(selected)
    setSummary(null)
    setError(null)
  }

  async function review(event: FormEvent) {
    event.preventDefault()
    if (!file) return
    if (!isAdifFileName(file.name)) {
      setError('El archivo debe ser un archivo ADIF (.adi o .adif).')
      return
    }
    if (file.size > MAX_FILE_BYTES) {
      setError('El archivo es demasiado grande. El máximo es 5 MB.')
      return
    }
    setBusy(true)
    setError(null)
    try {
      setSummary(await previewLog(activityId, file, isAdministrator ? operatorId : null))
    } catch (failure) {
      setError(uploadError(failure))
    } finally {
      setBusy(false)
    }
  }

  async function save(skipRepeated: boolean) {
    if (!file) return
    setBusy(true)
    setError(null)
    try {
      onDone(await saveLog(activityId, file, isAdministrator ? operatorId : null, skipRepeated))
    } catch (failure) {
      setError(uploadError(failure))
      setBusy(false)
    }
  }

  return (
    <div className="upload">
      <form className="form" onSubmit={review}>
        <h4>Subir un log</h4>
        {isAdministrator && (
          <label>
            Operador
            <select
              value={operatorId}
              onChange={(event) => {
                setOperatorId(Number(event.target.value))
                setSummary(null)
              }}
            >
              {accounts.length === 0 && <option value={user.id}>{user.callSign}</option>}
              {accounts.map((account) => (
                <option key={account.id} value={account.id}>
                  {account.callSign} · {account.name}
                </option>
              ))}
            </select>
          </label>
        )}
        <label>
          Archivo ADIF (.adi o .adif, hasta 5 MB)
          <input
            type="file"
            accept=".adi,.adif"
            onChange={(event) => selectFile(event.target.files?.[0] ?? null)}
            required
          />
        </label>
        {!summary && (
          <div className="actions">
            <button type="submit" disabled={busy || !file}>
              {busy ? 'Leyendo…' : 'Revisar el archivo'}
            </button>
            <button type="button" onClick={onCancel}>
              Cancelar
            </button>
          </div>
        )}
      </form>
      {summary && <SummaryView summary={summary} />}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {summary && (
        <div className="actions">
          {summary.validCount > 0 && (
            <button type="button" onClick={() => void save(false)} disabled={busy}>
              {busy ? 'Guardando…' : `Guardar ${summary.validCount} contactos`}
            </button>
          )}
          {summary.repeated.length > 0 && summary.validCount > summary.repeated.length && (
            <button type="button" onClick={() => void save(true)} disabled={busy}>
              Guardar sin los repetidos ({summary.validCount - summary.repeated.length})
            </button>
          )}
          <button type="button" onClick={onCancel} disabled={busy}>
            Cancelar
          </button>
        </div>
      )}
    </div>
  )
}

function SummaryView({ summary }: { summary: Summary }) {
  const warnings = warningTexts(summary)
  return (
    <div className="summary" aria-live="polite">
      <h4>Resumen de {summary.fileName}</h4>
      <p>
        Operador: <strong>{summary.operator.callSign}</strong>
      </p>
      {summary.validCount === 0 ? (
        <p className="error">El archivo no tiene registros válidos. No se puede guardar.</p>
      ) : (
        <p>
          <strong>{summary.validCount}</strong> {summary.validCount === 1 ? 'registro válido' : 'registros válidos'}.
        </p>
      )}
      {summary.invalid.length > 0 && (
        <>
          <p>
            {summary.invalid.length === 1
              ? '1 registro no es válido y no se va a guardar:'
              : `${summary.invalid.length} registros no son válidos y no se van a guardar:`}
          </p>
          <ul className="summary-list">
            {summary.invalid.map((record) => (
              <li key={record.record}>{invalidRecordText(record)}</li>
            ))}
          </ul>
        </>
      )}
      {summary.repeated.length > 0 && (
        <>
          <p className="warning-text">
            {summary.repeated.length === 1
              ? `1 registro ya está en el log en vivo de ${summary.operator.callSign}:`
              : `${summary.repeated.length} registros ya están en el log en vivo de ${summary.operator.callSign}:`}
          </p>
          <ul className="summary-list">
            {summary.repeated.map((record) => (
              <li key={record.record}>
                Registro {record.record}: {record.callSign}, {record.qsoAt.slice(0, 10)} {record.qsoAt.slice(11, 16)} UTC.
              </li>
            ))}
          </ul>
          <p className="hint">Puede guardarlos igual u omitirlos. El archivo original se guarda completo.</p>
        </>
      )}
      {warnings.length > 0 && (
        <>
          <p>Advertencias (los registros se guardan igual):</p>
          <ul className="summary-list">
            {warnings.map((text) => (
              <li key={text}>{text}</li>
            ))}
          </ul>
        </>
      )}
    </div>
  )
}
