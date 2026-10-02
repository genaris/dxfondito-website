import { PROGRAM } from '../program.ts'

export function SiteFooter() {
  return (
    <footer className="site-footer">
      <p>
        {PROGRAM.group} ({PROGRAM.groupShort}) · Diploma {PROGRAM.name}
      </p>
      <p>
        Todos los horarios están en UTC · <a href={PROGRAM.source.url}>{PROGRAM.source.text}</a>
      </p>
    </footer>
  )
}
