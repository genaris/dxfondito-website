import { useEffect, useState } from 'react'
import { activityDates, readSeasons } from '../../activities.ts'
import { readSeasonMail } from '../../mail.ts'
import type { SeasonMail } from '../../mail.ts'
import { href } from '../../router.ts'
import { SeasonSelect } from '../../SeasonSelect.tsx'

type State = { kind: 'loading' } | { kind: 'ready'; data: SeasonMail } | { kind: 'error' }

/**
 * The QSL mailer (FR-MAIL-1): the activities of a season with the state of their QSL messages, the certificates,
 * and the links to the address book and the general messages.
 */
export function MailPage() {
  const [seasons, setSeasons] = useState<number[]>([])
  const [season, setSeason] = useState<number | null>(null)
  const [state, setState] = useState<State>({ kind: 'loading' })

  useEffect(() => {
    readSeasons()
      .then((data) => {
        setSeasons(data.seasons.includes(data.current) ? data.seasons : [data.current, ...data.seasons])
        setSeason(data.current)
      })
      .catch(() => setState({ kind: 'error' }))
  }, [])

  useEffect(() => {
    if (season === null) return
    let active = true
    readSeasonMail(season)
      .then((data) => {
        if (active) setState({ kind: 'ready', data })
      })
      .catch(() => {
        if (active) setState({ kind: 'error' })
      })
    return () => {
      active = false
    }
  }, [season])

  return (
    <>
      <div className="title-row">
        <h2>Envíos por correo</h2>
        {season !== null && seasons.length > 0 && <SeasonSelect seasons={seasons} value={season} onChange={setSeason} />}
      </div>
      <p>
        <a href={href('/admin/mensajes')}>Mensajes generales</a> · <a href={href('/admin/libreta')}>Libreta de contactos</a>
      </p>
      {state.kind === 'loading' && <p>Cargando…</p>}
      {state.kind === 'error' && <p className="error">No se pudieron leer los envíos.</p>}
      {state.kind === 'ready' && (
        <>
          <section className="card mail-summary">
            <h3>Certificados {state.data.season}</h3>
            <p>
              {state.data.certificates.pending === 0 && state.data.certificates.changed === 0
                ? 'No hay certificados por enviar.'
                : `${state.data.certificates.pending} por enviar` +
                  (state.data.certificates.changed > 0 ? ` · ${state.data.certificates.changed} con cambios desde el envío` : '') +
                  '.'}
            </p>
            <a href={href(`/admin/envios/certificados/${state.data.season}`)}>Ver los certificados</a>
          </section>
          <section>
            <h3>QSL por actividad</h3>
            {state.data.activities.length === 0 && <p>La temporada no tiene actividades.</p>}
            {state.data.activities.length > 0 && (
              <div className="table-scroll">
                <table className="table">
                  <thead>
                    <tr>
                      <th>Actividad</th>
                      <th>Fecha</th>
                      <th className="number">Participantes</th>
                      <th className="number">Enviados</th>
                      <th className="number">Pendientes</th>
                    </tr>
                  </thead>
                  <tbody>
                    {state.data.activities.map((activity) => (
                      <tr key={activity.id}>
                        <td>
                          <a href={href(`/admin/envios/actividad/${activity.id}`)}>
                            {activity.reference.code} {activity.reference.name}
                          </a>
                        </td>
                        <td className="nowrap">{activityDates(activity)}</td>
                        <td className="number">{activity.participants}</td>
                        <td className="number">{activity.sent}</td>
                        <td className="number">{activity.pending > 0 ? <strong>{activity.pending}</strong> : 0}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}
    </>
  )
}
