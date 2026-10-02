import membrete from '../assets/membrete-puestos-de-salud.jpg'
import { levelName, PROGRAM } from '../program.ts'
import { href } from '../router.ts'

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
      <p className="hint">
        Fuente: <a href={PROGRAM.source.url}>{PROGRAM.source.text}</a>.
      </p>
    </article>
  )
}
