import { useEffect, useState } from 'react'
import { apiGet } from './api.ts'

interface Health {
  status: string
  database: boolean
  schema: string | null
}

type HealthState = { kind: 'loading' } | { kind: 'ready'; health: Health } | { kind: 'error' }

function App() {
  const [state, setState] = useState<HealthState>({ kind: 'loading' })

  useEffect(() => {
    let active = true
    apiGet<Health>('/health')
      .then((health) => {
        if (active) setState({ kind: 'ready', health })
      })
      .catch(() => {
        if (active) setState({ kind: 'error' })
      })
    return () => {
      active = false
    }
  }, [])

  return (
    <main>
      <h1>DX Fondito</h1>
      <p>Sitio en construcción.</p>
      <h2>Estado del sistema</h2>
      {state.kind === 'loading' && <p>Consultando…</p>}
      {state.kind === 'error' && <p>La API no responde.</p>}
      {state.kind === 'ready' && (
        <ul>
          <li>API: {state.health.status === 'ok' ? 'en línea' : 'con errores'}</li>
          <li>Base de datos: {state.health.database ? 'conectada' : 'sin conexión'}</li>
          <li>Esquema: {state.health.schema ?? 'sin migraciones'}</li>
        </ul>
      )}
    </main>
  )
}

export default App
