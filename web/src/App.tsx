import type { ReactNode } from 'react'
import { UsersPage } from './pages/admin/UsersPage.tsx'
import { ChangePasswordPage } from './pages/ChangePasswordPage.tsx'
import { HomePage } from './pages/HomePage.tsx'
import { SignInPage } from './pages/SignInPage.tsx'
import { href, useHashPath } from './router.ts'
import { useSession } from './useSession.ts'

function App() {
  const path = useHashPath()
  const { ready, user, signOut } = useSession()

  return (
    <>
      <header className="site-header">
        <a className="site-name" href={href('/')}>
          DX Fondito
        </a>
        {ready && (
          <nav className="session">
            {user ? (
              <>
                {user.role === 'administrator' && !user.mustChangePassword && (
                  <a href={href('/admin/usuarios')}>Cuentas</a>
                )}
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
      </header>
      <main>{ready ? <Page path={path} /> : <p>Cargando…</p>}</main>
    </>
  )
}

function Page({ path }: { path: string }) {
  const { user } = useSession()

  // A user with an initial password must change it before all other actions (FR-AUT-3).
  if (user?.mustChangePassword) return <ChangePasswordPage required />

  switch (path) {
    case '/':
      return <HomePage />
    case '/ingresar':
      return user ? <p>Ya ingresó como {user.callSign}.</p> : <SignInPage />
    case '/contrasena':
      return user ? <ChangePasswordPage required={false} /> : <SignInPage />
    case '/admin/usuarios':
      return <AdministratorOnly><UsersPage /></AdministratorOnly>
    default:
      return <p>La página no existe.</p>
  }
}

function AdministratorOnly({ children }: { children: ReactNode }) {
  const { user } = useSession()
  if (!user) return <SignInPage />
  // The API also checks the role. This check only hides the page.
  if (user.role !== 'administrator') return <p>Solo un administrador puede ver esta página.</p>
  return children
}

export default App
