import { useEffect, useState } from 'react'
import { activityHours, argentinaHours, readActivities, readSeasons } from '../activities.ts'
import type { Activity } from '../activities.ts'
import { activityState, levelName, longDate, nextActivity, nowUtc, PROGRAM } from '../program.ts'
import { href } from '../router.ts'

/**
 * The top of the home page: what the program is, its main rules and the next activity.
 */
export function ProgramHero() {
  return (
    <section className="hero">
      <div className="hero-text">
        <p className="eyebrow">{PROGRAM.tagline}</p>
        <h1>Diploma {PROGRAM.name}</h1>
        <p className="lead">{PROGRAM.purpose}</p>
        <ul className="facts-list">
          <li>
            <span className="fact-label">Bandas</span> {PROGRAM.bands}
          </li>
          <li>
            <span className="fact-label">Modo</span> {PROGRAM.modes}
          </li>
          <li>
            <span className="fact-label">Horarios</span> {PROGRAM.hours}
          </li>
        </ul>
        <p className="levels-line">
          {[5, 10, 15].map((points) => (
            <span key={points} className={`level-chip level-${levelName(points).toLowerCase()}`}>
              {levelName(points)} · {points} referencias
            </span>
          ))}
        </p>
        <p>
          <a className="button" href={href('/programa')}>
            Ver las bases del programa
          </a>
        </p>
      </div>
      <NextActivityCard />
    </section>
  )
}

type State = { kind: 'loading' } | { kind: 'ready'; activity: Activity | null } | { kind: 'error' }

function NextActivityCard() {
  const [state, setState] = useState<State>({ kind: 'loading' })

  useEffect(() => {
    let active = true
    const now = nowUtc()
    readSeasons()
      .then(async (seasons) => {
        // The next activity can be in the next season, at the end of the year.
        const lists = await Promise.all([readActivities(seasons.current), readActivities(seasons.current + 1)])
        if (active) setState({ kind: 'ready', activity: nextActivity(lists.flat(), now) })
      })
      .catch(() => {
        if (active) setState({ kind: 'error' })
      })
    return () => {
      active = false
    }
  }, [])

  if (state.kind === 'loading') return <aside className="next-card">Cargando la próxima actividad…</aside>
  if (state.kind === 'error') return null

  const { activity } = state
  if (!activity) {
    return (
      <aside className="next-card">
        <p className="eyebrow">Próxima actividad</p>
        <p>Pronto anunciamos la próxima activación.</p>
        <a href={href('/actividades')}>Ver las actividades</a>
      </aside>
    )
  }

  const onAir = activityState(activity, nowUtc())
  const hours = activityHours(activity)
  const localHours = argentinaHours(activity)
  const eyebrow = { live: '¡En el aire!', today: 'Hoy', later: 'Próxima actividad' }[onAir]
  return (
    <aside className={onAir === 'live' ? 'next-card live' : 'next-card'}>
      <p className="eyebrow">{eyebrow}</p>
      <p className="next-code">{activity.reference.code}</p>
      <h2>{activity.reference.name}</h2>
      <p className="next-date">
        {activity.startDate === activity.endDate
          ? longDate(activity.startDate)
          : `${longDate(activity.startDate)} al ${longDate(activity.endDate)}`}
      </p>
      <p className="next-hours">
        {hours ? (
          <>
            {hours}
            <span className="hint"> · {localHours} hora argentina</span>
          </>
        ) : (
          <span className="hint">Horario a confirmar</span>
        )}
      </p>
      {activity.description && <p className="prewrap next-description">{activity.description}</p>}
      <a href={href(`/actividad/${activity.id}`)}>Ver la actividad</a>
    </aside>
  )
}
