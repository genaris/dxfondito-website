import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, apiSend, apiUrl, setSessionToken } from './api.ts'

describe('apiUrl', () => {
  it('puts the path in the r parameter', () => {
    expect(apiUrl('/seasons/2026/ranking')).toBe('api/index.php?r=/seasons/2026/ranking')
  })

  it('adds the other parameters after the path', () => {
    expect(apiUrl('/seasons/2026/ranking', { search: 'LU1' })).toBe(
      'api/index.php?r=/seasons/2026/ranking&search=LU1',
    )
  })

  it('gives a relative address', () => {
    expect(apiUrl('/health').startsWith('/')).toBe(false)
  })

  it('keeps the & character in the path', () => {
    expect(apiUrl('/participants/A&B')).toBe('api/index.php?r=/participants/A%26B')
  })
})

describe('apiSend', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    setSessionToken(null)
  })

  function stubFetch(status: number, body: unknown) {
    const fetchMock = vi.fn(async () => new Response(JSON.stringify(body), { status }))
    vi.stubGlobal('fetch', fetchMock)
    return fetchMock
  }

  it('sends the token of the session and the body as JSON', async () => {
    const fetchMock = stubFetch(200, { ok: true })
    setSessionToken('abc')

    await apiSend('PUT', '/session/password', { newPassword: 'x' })

    const [url, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit]
    expect(url).toBe('api/index.php?r=/session/password')
    expect(init.method).toBe('PUT')
    expect(init.headers).toMatchObject({ 'X-CSRF-Token': 'abc', 'Content-Type': 'application/json' })
    expect(init.body).toBe('{"newPassword":"x"}')
  })

  it('sends no token without a session', async () => {
    const fetchMock = stubFetch(200, {})

    await apiSend('POST', '/session', {})

    const [, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit]
    expect(init.headers).not.toHaveProperty('X-CSRF-Token')
  })

  it('gives the status and the message of an error', async () => {
    stubFetch(423, { error: 'The account is locked' })

    const error = await apiSend('POST', '/session', {}).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ status: 423, message: 'The account is locked' })
  })
})
