import { useEffect, useState } from 'react'

// The pages use hash paths, such as /#/participante/LU1ABC.
// Thus the web server needs no rewrite rules.

export function pathFromHash(hash: string): string {
  const path = hash.replace(/^#/, '')
  return path.startsWith('/') ? path : `/${path}`
}

/**
 * Compares a path with a pattern such as /actividad/:id.
 * Gives the values of the parameters, or null if the path does not match.
 */
export function matchPath(pattern: string, path: string): Record<string, string> | null {
  const patternParts = pattern.split('/')
  const pathParts = path.split('/')
  if (patternParts.length !== pathParts.length) return null

  const params: Record<string, string> = {}
  for (const [index, part] of patternParts.entries()) {
    const value = pathParts[index]
    if (part.startsWith(':')) {
      if (value === '') return null
      params[part.slice(1)] = decodeURIComponent(value)
    } else if (part !== value) {
      return null
    }
  }
  return params
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
