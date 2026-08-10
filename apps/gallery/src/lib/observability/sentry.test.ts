import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { initMock, captureExceptionMock } = vi.hoisted(() => ({ initMock: vi.fn(), captureExceptionMock: vi.fn() }))

vi.mock('@sentry/node', () => ({
  init: initMock,
  captureException: captureExceptionMock,
}))

const ORIGINAL_ENV = { ...process.env }

beforeEach(() => {
  vi.resetModules()
  initMock.mockClear()
  captureExceptionMock.mockClear()
  process.env = { ...ORIGINAL_ENV }
})

afterEach(() => {
  process.env = { ...ORIGINAL_ENV }
})

// G-13/F-11's shared acceptance-critical bit: with an empty DSN — the
// default for CI and local dev — nothing is transmitted. Proven here against
// the real init wiring rather than inspection, mirroring apps/studio's
// EmptyDsnTest: Sentry.init is simply never called, so no client and no
// transport ever exist for a later capture call to reach.
describe('initObservability', () => {
  it('never calls Sentry.init when SENTRY_DSN is empty', async () => {
    delete process.env.SENTRY_DSN
    const { initObservability } = await import('./sentry')

    initObservability()

    expect(initMock).not.toHaveBeenCalled()
  })

  it('initializes with the gallery surface tag, the release and the scrub hook when a DSN is set', async () => {
    process.env.SENTRY_DSN = 'https://key@glitchtip.example/1'
    process.env.SENTRY_RELEASE = 'abc1234'
    const { initObservability } = await import('./sentry')

    initObservability()

    expect(initMock).toHaveBeenCalledTimes(1)
    const options = initMock.mock.calls[0][0]
    expect(options.dsn).toBe('https://key@glitchtip.example/1')
    expect(options.release).toBe('abc1234')
    expect(options.initialScope).toEqual({ tags: { surface: 'gallery' } })
    expect(typeof options.beforeSend).toBe('function')
  })

  it('initializes only once even when called again', async () => {
    process.env.SENTRY_DSN = 'https://key@glitchtip.example/1'
    const { initObservability } = await import('./sentry')

    initObservability()
    initObservability()

    expect(initMock).toHaveBeenCalledTimes(1)
  })
})

describe('captureApiFailure', () => {
  it('tags the captured exception with the gallery surface and the shared request id', async () => {
    const { captureApiFailure } = await import('./sentry')

    const error = new Error('api 502 for /api/galleries/gal_abc')
    captureApiFailure('req-shared-id', '/api/galleries/gal_abc', error)

    expect(captureExceptionMock).toHaveBeenCalledWith(error, {
      tags: { surface: 'gallery', request_id: 'req-shared-id' },
      extra: { path: '/api/galleries/gal_abc' },
    })
  })
})
