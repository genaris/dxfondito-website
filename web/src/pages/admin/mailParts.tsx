import { formatUtc } from '../../audit.ts'
import type { DeliveryInfo, Recipient } from '../../mail.ts'
import type { SendProgressState } from './useSendProgress.ts'

export function SendProgress({ progress }: { progress: SendProgressState }) {
  const done = progress.sent + progress.failed + progress.skipped
  if (!progress.running && done === 0) return null
  return (
    <div className="send-progress" role="status">
      <progress max={progress.total} value={done} />
      <span>
        {progress.running ? 'Enviando' : 'Terminado'}: {done} de {progress.total} · {progress.sent} enviados
        {progress.failed > 0 && ` · ${progress.failed} fallidos`}
        {progress.skipped > 0 && ` · ${progress.skipped} omitidos`}
      </span>
      {progress.waitingUntil && (
        <span className="hint">
          {' '}
          · Se llegó al límite de correos de la hora. Se retoma solo a las {formatUtc(progress.waitingUntil)}. No cierres
          esta página.
        </span>
      )}
      {progress.running && (
        <button type="button" className="secondary" onClick={progress.stop}>
          Detener
        </button>
      )}
    </div>
  )
}

/** The state of the last message or mark of a row. */
export function DeliveryStatus({ pending, last }: { pending: boolean; last: DeliveryInfo | null }) {
  if (pending) {
    return last?.status === 'failed' ? (
      <span className="status status-failed" title={last.error ?? undefined}>
        Falló · {formatUtc(last.at)}
      </span>
    ) : (
      <span className="status status-pending">Pendiente</span>
    )
  }
  if (!last) return <span className="status status-sent">Enviado</span>
  return last.method === 'manual' ? (
    <span className="status status-manual">Enviado a mano · {formatUtc(last.at).slice(0, 10)}</span>
  ) : (
    <span className="status status-sent" title={last.recipient ?? undefined}>
      Enviado · {formatUtc(last.at)}
    </span>
  )
}

/** The address of a row, its source, its warnings, and the link to the address book. */
export function EmailCell({ recipient, onEdit }: { recipient: Recipient; onEdit: () => void }) {
  return (
    <>
      {recipient.noMail ? (
        <span className="status status-failed">No enviar</span>
      ) : recipient.email ? (
        <>
          <span className="email">{recipient.email}</span>{' '}
          <span className="tag">{recipient.source === 'book' ? 'libreta' : 'log'}</span>
        </>
      ) : (
        <span className="hint">sin e-mail</span>
      )}
      {recipient.changedFrom && (
        <span className="warning-text" title="El e-mail es distinto del que recibió el último correo.">
          {' '}
          · cambió: antes {recipient.changedFrom}
        </span>
      )}
      {recipient.others.length > 0 && !recipient.changedFrom && (
        <span className="hint" title={recipient.others.join(', ')}>
          {' '}
          · +{recipient.others.length}
        </span>
      )}{' '}
      <button type="button" className="link" onClick={onEdit}>
        Editar
      </button>
    </>
  )
}
