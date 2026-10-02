import { useEffect, useState } from 'react'
import { readActivities } from '../activities.ts'
import type { Activity } from '../activities.ts'
import lu2aog from '../assets/activators/lu2aog.webp'
import lu2aoz from '../assets/activators/lu2aoz.webp'
import lu4alt from '../assets/activators/lu4alt.webp'
import lu5aea from '../assets/activators/lu5aea.webp'
import lu7bmw from '../assets/activators/lu7bmw.webp'
import lu9als from '../assets/activators/lu9als.webp'
import lw7eah from '../assets/activators/lw7eah.webp'
import membrete from '../assets/membrete-puestos-de-salud.jpg'
import { CALENDAR, levelName, longDate, PROGRAM, todayUtc } from '../program.ts'
import { href } from '../router.ts'

/**
 * The logos of the activators, in the order of the page of LU2AOZ on QRZ.com, with LU2AOZ first.
 * The logo of LU2AOZ is the logo of the group. The logos are WebP images with a transparent background.
 */
const ACTIVATORS = [
  { callSign: 'LU2AOZ', logo: lu2aoz },
  { callSign: 'LU9ALS', logo: lu9als },
  { callSign: 'LU5AEA', logo: lu5aea },
  { callSign: 'LW7EAH', logo: lw7eah },
  { callSign: 'LU2AOG', logo: lu2aog },
  { callSign: 'LU7BMW', logo: lu7bmw },
  { callSign: 'LU4ALT', logo: lu4alt },
]

/**
 * The rules of the program "Diplomas Puestos de Salud".
 */
export function ProgramPage() {
  return (
    <article className="program">
      <img
        className="banner"
        src={membrete}
        alt={`${PROGRAM.tagline} ${PROGRAM.name}, ${PROGRAM.group}`}
        width={1198}
        height={321}
      />
      <h2>El programa</h2>
      <p className="lead">{PROGRAM.purpose}</p>
      <p>{PROGRAM.stations}</p>

      <div className="cards">
        <section className="card">
          <h3>Temporada</h3>
          <p>{PROGRAM.season}</p>
          <p>
            Las fechas de cada activación están en <a href={href('/actividades')}>Actividades</a>.
          </p>
        </section>
        <section className="card">
          <h3>Bandas y modos</h3>
          <p>
            {PROGRAM.bands}. {PROGRAM.modes}.
          </p>
          <p>{PROGRAM.power}</p>
        </section>
        <section className="card">
          <h3>Horarios</h3>
          <p>{PROGRAM.schedule}</p>
          <p>
            <strong>{PROGRAM.utc}</strong>
          </p>
        </section>
        <section className="card">
          <h3>QSL Especial</h3>
          <p>{PROGRAM.qsl}</p>
          <p>{PROGRAM.listeners}</p>
        </section>
      </div>

      <SeasonCalendar />

      <h3>Certificados</h3>
      <p>{PROGRAM.certificateRule}</p>
      <div className="levels">
        {[5, 10, 15].map((points) => (
          <div key={points} className={`level-card level-${levelName(points).toLowerCase()}`}>
            <span className="level-name">{levelName(points)}</span>
            <span className="level-points">{points}</span>
            <span>referencias distintas</span>
          </div>
        ))}
      </div>
      <h3>Activadores</h3>
      <p>{PROGRAM.activators}</p>
      <ul className="activators">
        {ACTIVATORS.map((activator) => (
          <li key={activator.callSign}>
            <a
              href={`https://www.qrz.com/db/${activator.callSign}`}
              target="_blank"
              rel="noopener noreferrer"
              title={`Perfil de ${activator.callSign} en QRZ.com`}
            >
              <img src={activator.logo} alt={`Logo de ${activator.callSign}`} width={120} height={120} loading="lazy" />
              <span>{activator.callSign}</span>
            </a>
          </li>
        ))}
      </ul>
      <p className="hint">
        Novedades y consultas en el{' '}
        <a href={PROGRAM.facebook.url} target="_blank" rel="noopener noreferrer">
          grupo de Facebook
        </a>
        .
      </p>
    </article>
  )
}

/**
 * The planned events of the season, with a link to each activity that the group already loaded.
 * The past events are pale, and the next event has a mark.
 */
function SeasonCalendar() {
  const [activities, setActivities] = useState<Activity[]>([])

  useEffect(() => {
    readActivities(CALENDAR.season)
      .then(setActivities)
      .catch(() => setActivities([]))
  }, [])

  const today = todayUtc()
  const next = CALENDAR.events.find((event) => event.date >= today)
  return (
    <section>
      <h3>Calendario {CALENDAR.season}</h3>
      <p className="hint">
        Fechas previstas: pueden cambiar, y se pueden sumar efemérides. Los horarios y las novedades de cada
        activación están en <a href={href('/actividades')}>Actividades</a>.
      </p>
      <ol className="calendar">
        {CALENDAR.events.map((event) => {
          const activity = activities.find(
            (item) =>
              item.startDate <= event.date && item.endDate >= event.date && item.reference.code.startsWith(event.series),
          )
          const state = event === next ? 'next' : event.date < today ? 'past' : undefined
          return (
            <li key={`${event.date}-${event.name}`} className={state}>
              <span className="calendar-date">{longDate(event.date)}</span>
              <span className={`tag series-${event.series.toLowerCase()}`}>{event.series}</span>
              <span className="calendar-name">
                {event.name}
                {activity && (
                  <>
                    {' · '}
                    <a href={href(`/actividad/${activity.id}`)}>{activity.reference.code}</a>
                  </>
                )}
              </span>
              {state === 'next' && <span className="calendar-next">Próxima</span>}
            </li>
          )
        })}
      </ol>
    </section>
  )
}
