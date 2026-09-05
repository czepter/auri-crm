import type { APIContext, MiddlewareNext } from 'astro'
import { describe, expect, it, vi } from 'vitest'

vi.mock('./lib/observability/sentry', () => ({ initObservability: vi.fn() }))

const { onRequest } = await import('./middleware')

function buildContext(headers: HeadersInit): { context: APIContext; locals: Record<string, unknown> } {
  const locals: Record<string, unknown> = {}

  return {
    locals,
    context: { request: new Request('https://gallery.example/g/gal_abc', { headers }), locals } as unknown as APIContext,
  }
}

// G-13's Astro-side AssignRequestId: same three behaviours as apps/studio's
// RequestIdTest, on this side of the request instead — accept, mint, echo.
describe('onRequest', () => {
  it('accepts the edge-supplied X-Request-Id, stores it on locals and echoes it back', async () => {
    const { context, locals } = buildContext({ 'X-Request-Id': 'edge-supplied-id' })
    const next: MiddlewareNext = vi.fn().mockResolvedValue(new Response(null))

    const response = await onRequest(context, next)

    expect(locals.requestId).toBe('edge-supplied-id')
    expect((response as Response).headers.get('X-Request-Id')).toBe('edge-supplied-id')
  })

  it('mints an id when the caller sent none, stores and echoes that one', async () => {
    const { context, locals } = buildContext({})
    const next: MiddlewareNext = vi.fn().mockResolvedValue(new Response(null))

    const response = await onRequest(context, next)

    expect(locals.requestId).toMatch(/^[0-9a-f-]{36}$/)
    expect((response as Response).headers.get('X-Request-Id')).toBe(locals.requestId)
  })
})
