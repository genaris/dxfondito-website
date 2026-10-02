import type { ReactNode } from 'react'
import { ActivitiesAdminPage } from './pages/admin/ActivitiesAdminPage.tsx'
import { AuditPage } from './pages/admin/AuditPage.tsx'
import { ReferencesPage } from './pages/admin/ReferencesPage.tsx'
import { UsersPage } from './pages/admin/UsersPage.tsx'
import { ActivitiesPage } from './pages/ActivitiesPage.tsx'
import { ActivityPage } from './pages/ActivityPage.tsx'
import { ChangePasswordPage } from './pages/ChangePasswordPage.tsx'
import { HomePage } from './pages/HomePage.tsx'
import { LogPage } from './pages/LogPage.tsx'
import { SignInPage } from './pages/SignInPage.tsx'
import { href, matchPath, useHashPath } from './router.ts'
import { useSession } from './useSession.ts'

const ADMIN_LINKS = [
  { path: '/admin/referencias', text: 'Referencias' },
  { path: '/admin/actividades', text: 'Actividades' },
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
        <div className="header-row">
          <a className="site-name" href={href('/')}>
            DX Fondito
          </a>
          <nav className="site-nav" aria-label="Secciones">
            <NavLink path="/actividades" current={path}>
              Actividades
            </NavLink>
          </nav>
          {ready && (
            <nav className="session" aria-label="Sesión">
              {user ? (
                <>
                  <span>{user.callSign}</span>
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
            {ADMIN_LINKS.map((link) => (
              <NavLink key={link.path} path={link.path} current={path}>
                {link.text}
              </NavLink>
            ))}
          </nav>
        )}
      </header>
      <main>{ready ? <Page path={path} /> : <p>Cargando…</p>}</main>
    </>
  )
}

function NavLink({ path, current, children }: { path: string; current: string; children: ReactNode }) {
  const active = current === path || current.startsWith(`${path}/`)
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

  const logPage = matchPath('/log/:id', path)
  if (logPage) {
    if (!user) return <SignInPage />
    return /^\d+$/.test(logPage.id) ? <LogPage key={logPage.id} id={Number(logPage.id)} /> : <NotFound />
  }

  switch (path) {
    case '/':
      return <HomePage />
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
