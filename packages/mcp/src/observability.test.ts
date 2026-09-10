import { init } from '@sentry/node'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { initObservability, scrubEvent } from './observability.js'

// The real module cannot be spied on in ESM, and F-11's empty-DSN
// convention is exactly the case worth pinning: an unconfigured deploy makes
// no outbound call at all, because no client is ever created.
vi.mock('@sentry/node', () => ({ init: vi.fn(), captureException: vi.fn() }))

describe('initObservability', () => {
  afterEach(() => {
    vi.unstubAllEnvs()
  })

  it('never configures a client without a DSN', () => {
    vi.stubEnv('SENTRY_DSN', '')

    initObservability()

    expect(init).not.toHaveBeenCalled()
  })
})

describe('scrubEvent', () => {
  it('drops the request record that would carry the bearer token', () => {
    const event = { request: { headers: { authorization: 'Bearer secret' } }, message: 'boom' }

    expect(scrubEvent(event)).toEqual({ message: 'boom' })
  })
})
