import { useEffect, useState } from 'react'

// The pages use hash paths, such as /#/participante/LU1ABC.
// Thus the web server needs no rewrite rules.

export function pathFromHash(hash: string): string {
  const path = hash.replace(/^#/, '')
  return path.startsWith('/') ? path : `/${path}`
}

export function href(path: string): string {
  return `#${path}`
}

export function navigate(path: string): void {
  window.location.hash = path
}

export function useHashPath(): string {
  const [path, setPath] = useState(() => pathFromHash(window.location.hash))

  useEffect(() => {
    const update = () => setPath(pathFromHash(window.location.hash))
    window.addEventListener('hashchange', update)
    return () => window.removeEventListener('hashchange', update)
  }, [])

  return path
}
