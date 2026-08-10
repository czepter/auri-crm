import { afterEach, describe, expect, it, vi } from 'vitest'
import { callApi } from './apiClient'

const { captureApiFailureMock } = vi.hoisted(() => ({ captureApiFailureMock: vi.fn() }))

vi.mock('./observability/sentry', () => ({
  captureApiFailure: captureApiFailureMock,
}))

afterEach(() => {
  vi.unstubAllGlobals()
  captureApiFailureMock.mockClear()
})

describe('callApi', () => {
  it('forwards X-Request-Id on the outgoing call, the same id it was given', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response('{}', { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await callApi('https://api.example', '/api/galleries/gal_abc', 'req-shared-id')

    const [, init] = fetchMock.mock.calls[0]
    const headers = init.headers as Headers
    expect(headers.get('X-Request-Id')).toBe('req-shared-id')
  })

  it('never reports a successful response', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{}', { status: 200 })))

    await callApi('https://api.example', '/api/galleries/gal_abc', 'req-1')

    expect(captureApiFailureMock).not.toHaveBeenCalled()
  })

  // G-13's acceptance criterion: a wrong gallery password (or any other 4xx
  // the api surface answers with — not found, unauthenticated, a gate not
  // yet satisfied) is expected traffic, never an event. Modelled on
  // EnsureAudience's own 401 JSON shape, the closest thing this codebase's
  // api surface already has to a gate failure.
  it('produces no event for a 401 shaped like a wrong gallery password', async () => {
    const response = new Response(JSON.stringify({ message: 'Unauthenticated.' }), { status: 401 })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response))

    const result = await callApi('https://api.example', '/api/galleries/gal_abc', 'req-1')

    expect(captureApiFailureMock).not.toHaveBeenCalled()
    expect(result.status).toBe(401)
  })

  it('produces no event for any other 4xx either', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 404 })))

    await callApi('https://api.example', '/api/galleries/gal_abc', 'req-1')

    expect(captureApiFailureMock).not.toHaveBeenCalled()
  })

  it('reports a 5xx as a gallery outage, tagged with the same request id it forwarded', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 502 })))

    const result = await callApi('https://api.example', '/api/galleries/gal_abc', 'req-shared-id')

    expect(result.status).toBe(502)
    expect(captureApiFailureMock).toHaveBeenCalledTimes(1)
    const [requestId, path, error] = captureApiFailureMock.mock.calls[0]
    expect(requestId).toBe('req-shared-id')
    expect(path).toBe('/api/galleries/gal_abc')
    expect(error).toBeInstanceOf(Error)
  })

  it('reports a network failure as a gallery outage and degrades to a synthetic 502', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('fetch failed')))

    const result = await callApi('https://api.example', '/api/galleries/gal_abc', 'req-shared-id')

    expect(result.status).toBe(502)
    expect(captureApiFailureMock).toHaveBeenCalledTimes(1)
    expect(captureApiFailureMock.mock.calls[0][0]).toBe('req-shared-id')
  })
})
