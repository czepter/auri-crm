import { defineMiddleware } from 'astro/middleware'
import { initObservability } from './lib/observability/sentry'
import { resolveRequestId } from './lib/observability/requestId'

// G-13: mirrors apps/studio's own bootstrap/app.php, which registers
// AssignRequestId ahead of everything else and configures the Sentry scope
// once per process rather than per request (see ObservabilityServiceProvider
// and TagSurface's own doc comments for why "once" is correct there) — this
// module runs once when the SSR process starts, for the same reason.
initObservability()

/**
 * G-13's Astro-side AssignRequestId: accepts `X-Request-Id` from the edge
 * when present, mints one otherwise, stores it on `Astro.locals.requestId`
 * for every page and outgoing api call this request makes
 * (src/lib/apiClient.ts), and echoes it back on the response — exactly the
 * three things the Laravel middleware does.
 */
export const onRequest = defineMiddleware(async (context, next) => {
  const requestId = resolveRequestId(context.request.headers.get('X-Request-Id'))
  context.locals.requestId = requestId

  const response = await next()
  response.headers.set('X-Request-Id', requestId)

  return response
})
