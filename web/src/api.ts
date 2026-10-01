// The API has one entry file. The `r` parameter gives the path of the request.
const API_ENTRY = 'api/index.php'

export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
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
  if (!response.ok) {
    throw new ApiError(response.status, `API request failed: ${path}`)
  }
  return (await response.json()) as T
}
