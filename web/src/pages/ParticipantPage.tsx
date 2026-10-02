import { useEffect, useState } from 'react'
import { frequencyText } from '../logs.ts'
import { readParticipant } from '../ranking.ts'
import type { Participant, ParticipantSeason } from '../ranking.ts'
import { href } from '../router.ts'

type State = { kind: 'loading' } | { kind: 'ready'; participant: Participant } | { kind: 'error' }

/**
 * The page of a participant: each season with the points, the references and the certificates
 * (FR-PUB-8 to FR-PUB-12). The current season comes first.
 */
export function ParticipantPage({ callSign }: { callSign: string }) {
  const [state, setState] = useState<State>({ kind: 'loading' })

  useEffect(() => {
    // The page has a new instance for each call sign. Thus the first state is 'loading'.
    let active = true
    readParticipant(callSign)
      .then((participant) => {
        if (active) setState({ kind: 'ready', participant })
      })
      .catch(() => {
        if (active) setState({ kind: 'error' })
      })
    return () => {
      active = false
    }
  }, [callSign])

  if (state.kind === 'loading') return <p>Cargando…</p>
  if (state.kind === 'error') return <p className="error">No se pudieron leer los datos de {callSign}.</p>

  const { participant } = state
  return (
    <>
      <h2>{participant.callSign}</h2>
      {participant.seasons.length === 0 && <p>{participant.callSign} no tiene contactos con las actividades del grupo.</p>}
      {participant.seasons.map((season) => (
        <SeasonSection
          key={season.season}
          season={season}
          baseCallSign={participant.callSign}
          isCurrent={season.season === participant.current}
        />
      ))}
    </>
  )
}

function SeasonSection({
  season,
  baseCallSign,
  isCurrent,
}: {
  season: ParticipantSeason
  baseCallSign: string
  isCurrent: boolean
}) {
  return (
    <section className="season">
      <h3>
        Temporada {season.season}
        {isCurrent && ' (actual)'}
      </h3>
      <p>
        <strong>{season.points}</strong> {season.points === 1 ? 'punto' : 'puntos'}.{' '}
        {season.pointsToNextLevel !== null &&
          `Le ${season.pointsToNextLevel === 1 ? 'falta 1 punto' : `faltan ${season.pointsToNextLevel} puntos`} para el próximo certificado.`}
      </p>
      {season.certificates.length > 0 && (
        <p>
          Certificados:{' '}
          {season.certificates.map((certificate) => (
            <span key={certificate.points} className="badge" title={`Fecha: ${certificate.date}`}>
              {certificate.points} puntos · {certificate.date}
            </span>
          ))}
        </p>
      )}
      <div className="table-scroll">
        <table className="table">
          <thead>
            <tr>
              <th>Referencia</th>
              <th>Fecha y hora (UTC)</th>
              <th>Operador</th>
              <th>Frecuencia</th>
              <th>Modo</th>
            </tr>
          </thead>
          <tbody>
            {season.references.map((contact) => (
              <tr key={contact.referenceId}>
                <td>
                  <a href={href(`/actividad/${contact.activityId}`)}>{contact.reference}</a> {contact.referenceName}
                  {contact.callSign !== baseCallSign && (
                    <span className="hint"> · como {contact.callSign}</span>
                  )}
                </td>
                <td className="nowrap">
                  {contact.qsoAt.slice(0, 10)} {contact.qsoAt.slice(11, 16)}
                </td>
                <td>{contact.operator}</td>
                <td className="nowrap">{frequencyText(contact)}</td>
                <td>{contact.mode}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}
