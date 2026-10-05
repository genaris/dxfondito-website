import { useEffect, useState } from 'react'
import { readMailSummary } from '../mail.ts'
import { href } from '../router.ts'

/**
 * The warning for an administrator: certificates that wait for a message, or that changed after it (FR-MAIL-13).
 * The page asks again when the path changes, so the warning goes away after a sending.
 */
export function MailNotice({ path }: { path: string }) {
  const [summary, setSummary] = useState<{ pending: number; changed: number } | null>(null)

  useEffect(() => {
    let active = true
    readMailSummary()
      .then((data) => {
        if (active) setSummary(data.certificates)
      })
      .catch(() => {
        if (active) setSummary(null)
      })
    return () => {
      active = false
    }
  }, [path])

  if (!summary || (summary.pending === 0 && summary.changed === 0) || path.startsWith('/admin/envios')) return null
  const parts = [
    summary.pending > 0 && `${summary.pending} ${summary.pending === 1 ? 'certificado por enviar' : 'certificados por enviar'}`,
    summary.changed > 0 && `${summary.changed} con cambios desde el envío`,
  ].filter(Boolean)
  return (
    <div className="mail-notice" role="status">
      Hay {parts.join(' y ')}. <a href={href('/admin/envios')}>Ver los envíos</a>
    </div>
  )
}
