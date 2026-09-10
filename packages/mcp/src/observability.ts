import * as Sentry from '@sentry/node'

/**
 * This process's half of F-11's GlitchTip spine, same shape as
 * apps/gallery's src/lib/observability/sentry.ts: the generic `SENTRY_DSN`
 * (empty by default, which means `init` is never called and every capture
 * below is a no-op with zero outbound calls), the same `SENTRY_RELEASE` a
 * deploy already bakes in, tracing off.
 *
 * It exists because N-04's transport had no way to be seen failing: the MCP
 * SDK answers a protocol error with a 400 JSON-RPC body and a call to
 * `transport.onerror` — nothing throws, so no uncaught-exception hook and no
 * healthcheck (which only ever reads the metadata document, in front of the
 * gate) could report it. A shared transport rejected every connect after the
 * first for a whole release without a single event anywhere.
 */
let initialized = false

export function initObservability(): void {
  if (initialized) return
  initialized = true

  const dsn = process.env.SENTRY_DSN ?? ''
  if (!dsn) return

  Sentry.init({
    dsn,
    release: process.env.SENTRY_RELEASE || undefined,
    environment: process.env.SENTRY_ENVIRONMENT || undefined,
    tracesSampleRate: 0,
    initialScope: { tags: { surface: 'mcp' } },
    beforeSend: scrubEvent,
  })
}

/**
 * Every request here carries a staff user's own OAuth bearer in an
 * Authorization header. Sentry keeps headers out of an event without
 * `sendDefaultPii`, so this is the belt to that braces — dropping the
 * whole request record rather than redacting field by field, because
 * nothing in it is worth an event this process would otherwise never send.
 */
export function scrubEvent<T extends { request?: unknown }>(event: T): T {
  delete event.request

  return event
}

/**
 * The MCP SDK's own error channel (`Transport.onerror`), which is where a
 * rejected handshake surfaces — as a return value everywhere else.
 */
export function captureTransportError(error: Error): void {
  Sentry.captureException(error, { tags: { surface: 'mcp' } })
}
