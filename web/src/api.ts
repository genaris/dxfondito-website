// The API has one entry file. The `r` parameter gives the path of the request.
const API_ENTRY = 'api/index.php'

// The token of the session. Each request that changes data sends it in this header.
const TOKEN_HEADER = 'X-CSRF-Token'
let sessionToken: string | null = null

export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

export function setSessionToken(token: string | null): void {
  sessionToken = token
}

export function apiUrl(path: string, params: Record<string, string> = {}): string {
  const query = new URLSearchParams(params).toString()
  // The path stays readable: encodeURI does not change the `/` character.
  const route = encodeURI(path).replaceAll('&', '%26')
  return `${API_ENTRY}?r=${route}${query ? `&${query}` : ''}`
}

export async function apiGet<T>(path: string, params: Record<string, string> = {}): Promise<T> {
  const response = await fetch(apiUrl(path, params), {
    headers: { Accept: 'application/json' },
  })
  return readResponse<T>(response, path)
}

export async function apiSend<T>(
  method: 'POST' | 'PUT' | 'DELETE',
  path: string,
  body?: unknown,
): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (sessionToken !== null) headers[TOKEN_HEADER] = sessionToken

  const response = await fetch(apiUrl(path), {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  return readResponse<T>(response, path)
}

async function readResponse<T>(response: Response, path: string): Promise<T> {
  if (!response.ok) {
    const data = (await response.json().catch(() => null)) as { error?: string } | null
    throw new ApiError(response.status, data?.error ?? `API request failed: ${path}`)
  }
  return (await response.json()) as T
}
