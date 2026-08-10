import { describe, expect, it } from 'vitest'

import { idempotencyKeyFor } from './idempotency.js'

// I-03's EnsureIdempotentWrite dedupes on (tenant, api_token, key) — the
// contract this helper has to hold is: same logical call (same MCP request
// id, same session) -> same key, every time, so a client-side retry never
// double-writes; a different call -> a different key, so unrelated writes
// never collide.

describe('idempotencyKeyFor', () => {
  it('is stable for the same tool, session and request id', () => {
    const extra = { requestId: 42, sessionId: 'session-1' }

    expect(idempotencyKeyFor('capture_lead', extra)).toBe(idempotencyKeyFor('capture_lead', extra))
  })

  it('differs when the request id differs (a genuinely new call)', () => {
    const first = idempotencyKeyFor('capture_lead', { requestId: 1, sessionId: 'session-1' })
    const second = idempotencyKeyFor('capture_lead', { requestId: 2, sessionId: 'session-1' })

    expect(first).not.toBe(second)
  })

  it('differs across tools for the same request id, so two tools sharing a counter never collide', () => {
    const extra = { requestId: 1, sessionId: 'session-1' }

    expect(idempotencyKeyFor('capture_lead', extra)).not.toBe(idempotencyKeyFor('add_internal_note', extra))
  })

  it('differs across sessions for the same request id', () => {
    const first = idempotencyKeyFor('capture_lead', { requestId: 1, sessionId: 'session-1' })
    const second = idempotencyKeyFor('capture_lead', { requestId: 1, sessionId: 'session-2' })

    expect(first).not.toBe(second)
  })

  it('falls back to a fresh key when no request id is available, rather than colliding', () => {
    const first = idempotencyKeyFor('capture_lead', {})
    const second = idempotencyKeyFor('capture_lead', {})

    expect(first).not.toBe(second)
  })
})
