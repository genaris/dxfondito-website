import type { ReactNode } from 'react'
import escudo from './assets/escudo-fondito.jpg'
import { ProgramHero } from './components/ProgramHero.tsx'
import { SiteFooter } from './components/SiteFooter.tsx'
import { ActivitiesAdminPage } from './pages/admin/ActivitiesAdminPage.tsx'
import { AuditPage } from './pages/admin/AuditPage.tsx'
import { CertificatesPage } from './pages/admin/CertificatesPage.tsx'
import { ReferencesPage } from './pages/admin/ReferencesPage.tsx'
import { UsersPage } from './pages/admin/UsersPage.tsx'
import { ActivitiesPage } from './pages/ActivitiesPage.tsx'
import { ActivityPage } from './pages/ActivityPage.tsx'
import { ChangePasswordPage } from './pages/ChangePasswordPage.tsx'
import { HealthPage } from './pages/HealthPage.tsx'
import { LogPage } from './pages/LogPage.tsx'
import { ParticipantPage } from './pages/ParticipantPage.tsx'
import { ProgramPage } from './pages/ProgramPage.tsx'
import { RankingPage } from './pages/RankingPage.tsx'
import { SignInPage } from './pages/SignInPage.tsx'
import { PROGRAM } from './program.ts'
import { baseCallSign } from './ranking.ts'
import { href, matchPath, useHashPath } from './router.ts'
import { useSession } from './useSession.ts'

const ADMIN_LINKS = [
  { path: '/admin/referencias', text: 'Referencias' },
  { path: '/admin/actividades', text: 'Actividades' },
  { path: '/admin/certificados', text: 'Certificados' },
  { path: '/admin/usuarios', text: 'Cuentas' },
  { path: '/admin/registro', text: 'Registro' },
]

function App() {
  const path = useHashPath()
  const { ready, user, signOut } = useSession()
  const showAdmin = user?.role === 'administrator' && !user.mustChangePassword

  return (
    <>
      <header className="site-header">
        <div className="flag-stripe" aria-hidden="true" />
        <div className="header-row">
          <a className="brand" href={href('/')}>
            <img src={escudo} alt="" width={56} height={56} />
            <span className="brand-text">
              <span className="brand-group">{PROGRAM.group}</span>
              <span className="brand-name">{PROGRAM.name}</span>
              <span className="brand-tagline">{PROGRAM.tagline}</span>
            </span>
          </a>
          <nav className="site-nav" aria-label="Secciones">
            <NavLink path="/" current={path} isActive={(current) => current === '/' || current.startsWith('/temporada/')}>
              Ranking
            </NavLink>
            <NavLink path="/actividades" current={path} isActive={(current) => current.startsWith('/actividad')}>
              Actividades
            </NavLink>
            <NavLink path="/programa" current={path}>
              El programa
            </NavLink>
          </nav>
          {ready && (
            <nav className="session" aria-label="Sesión">
              {user ? (
                <>
                  <span className="session-user">{user.callSign}</span>
                  {!user.mustChangePassword && <a href={href('/contrasena')}>Contraseña</a>}
                  <button type="button" className="link" onClick={() => void signOut()}>
                    Salir
                  </button>
                </>
              ) : (
                path !== '/ingresar' && <a href={href('/ingresar')}>Ingresar</a>
              )}
            </nav>
          )}
        </div>
        {showAdmin && (
          <nav className="admin-nav" aria-label="Administración">
            <span className="admin-label">Administración</span>
            {ADMIN_LINKS.map((link) => (
              <NavLink key={link.path} path={link.path} current={path}>
                {link.text}
              </NavLink>
            ))}
          </nav>
        )}
      </header>
      {path === '/' && ready && !user?.mustChangePassword && <ProgramHero />}
      <main>{ready ? <Page path={path} /> : <p>Cargando…</p>}</main>
      <SiteFooter />
    </>
  )
}

function NavLink({
  path,
  current,
  isActive = (value) => value === path || value.startsWith(`${path}/`),
  children,
}: {
  path: string
  current: string
  isActive?: (current: string) => boolean
  children: ReactNode
}) {
  const active = isActive(current)
  return (
    <a href={href(path)} aria-current={active ? 'page' : undefined}>
      {children}
    </a>
  )
}

function Page({ path }: { path: string }) {
  const { user } = useSession()

  // A user with an initial password must change it before all other actions (FR-AUT-3).
  if (user?.mustChangePassword) return <ChangePasswordPage required />

  const seasonPage = matchPath('/actividades/:season', path)
  if (seasonPage) {
    return /^\d{4}$/.test(seasonPage.season) ? (
      <ActivitiesPage key={seasonPage.season} season={Number(seasonPage.season)} />
    ) : (
      <NotFound />
    )
  }
  const activityPage = matchPath('/actividad/:id', path)
  if (activityPage) {
    return /^\d+$/.test(activityPage.id) ? <ActivityPage key={activityPage.id} id={Number(activityPage.id)} /> : <NotFound />
  }

  const rankingPage = matchPath('/temporada/:season', path)
  if (rankingPage) {
    return /^\d{4}$/.test(rankingPage.season) ? (
      <RankingPage key={rankingPage.season} season={Number(rankingPage.season)} />
    ) : (
      <NotFound />
    )
  }
  const participantPage = matchPath('/participante/:call', path)
  if (participantPage) {
    const callSign = baseCallSign(participantPage.call)
    return /^[A-Z0-9]{3,20}$/.test(callSign) ? <ParticipantPage key={callSign} callSign={callSign} /> : <NotFound />
  }
  const logPage = matchPath('/log/:id', path)
  if (logPage) {
    if (!user) return <SignInPage />
    return /^\d+$/.test(logPage.id) ? <LogPage key={logPage.id} id={Number(logPage.id)} /> : <NotFound />
  }

  switch (path) {
    case '/':
      return <RankingPage season={null} />
    case '/programa':
      return <ProgramPage />
    case '/estado':
      return <HealthPage />
    case '/actividades':
      return <ActivitiesPage season={null} />
    case '/ingresar':
      return user ? <p>Ya ingresó como {user.callSign}.</p> : <SignInPage />
    case '/contrasena':
      return user ? <ChangePasswordPage required={false} /> : <SignInPage />
    case '/admin/referencias':
      return (
        <AdministratorOnly>
          <ReferencesPage />
        </AdministratorOnly>
      )
    case '/admin/actividades':
      return (
        <AdministratorOnly>
          <ActivitiesAdminPage />
        </AdministratorOnly>
      )
    case '/admin/usuarios':
      return (
        <AdministratorOnly>
          <UsersPage />
        </AdministratorOnly>
      )
    case '/admin/certificados':
      return (
        <AdministratorOnly>
          <CertificatesPage />
        </AdministratorOnly>
      )
    case '/admin/registro':
      return (
        <AdministratorOnly>
          <AuditPage />
        </AdministratorOnly>
      )
    default:
      return <NotFound />
  }
}

function NotFound() {
  return <p>La página no existe.</p>
}

function AdministratorOnly({ children }: { children: ReactNode }) {
  const { user } = useSession()
  if (!user) return <SignInPage />
  // The API also checks the role. This check only hides the page.
  if (user.role !== 'administrator') return <p>Solo un administrador puede ver esta página.</p>
  return children
}

export default App
