import * as Sentry from '@sentry/node'
import { scrubEvent } from './scrub'

/**
 * G-13: this SSR server's own half of F-11's GlitchTip spine — server-side
 * only, on purpose (see the issue's own rationale: no public DSN on an
 * anonymous page, no client-side weight on the surface G-08 keeps almost
 * bare, no third-party-looking beacon needing a consent banner). Nothing in
 * this module ever runs in a browser; apps/gallery ships no client bundle
 * that imports it.
 *
 * `SENTRY_DSN` is the same generic env var apps/studio's config/sentry.php
 * falls back to (`env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN', ''))`) — one
 * GlitchTip project can back both apps unless a deploy sets the
 * Laravel-specific override. Empty (the default) means `init` is never
 * called at all: no client is ever configured, so every capture call below
 * is a guaranteed no-op with zero outbound calls — matching F-11's own
 * empty-DSN convention exactly, not just approximating it.
 */
let initialized = false

export function initObservability(): void {
  if (initialized) return
  initialized = true

  const dsn = process.env.SENTRY_DSN ?? ''
  if (!dsn) return

  Sentry.init({
    dsn,
    // Baked in at deploy time from the same git SHA F-11 already bakes into
    // apps/studio's SENTRY_RELEASE (docker/php/Dockerfile's GIT_SHA build
    // arg) — reusing the identical env var name, not a second mechanism, so
    // one deploy reads as one release across both applications.
    release: process.env.SENTRY_RELEASE || undefined,
    environment: process.env.SENTRY_ENVIRONMENT || undefined,
    // Error visibility, not APM — same call F-11 makes and for the same
    // reason (config/sentry.php's own doc comment on traces_sample_rate).
    tracesSampleRate: 0,
    initialScope: { tags: { surface: 'gallery' } },
    beforeSend: (event) => scrubEvent(event),
  })
}

/**
 * G-13's crux: the gallery outage as the guest saw it — a 5xx or a timeout
 * from an outgoing call to the api surface (src/lib/apiClient.ts is the only
 * caller). Tagged with the same X-Request-Id this SSR request forwarded on
 * that outgoing call, so it and whatever Laravel already logged for that
 * same request (F-11) are one incident, findable by one id, not two
 * unrelated events in two systems.
 *
 * A wrong gallery password or PIN never reaches here — apiClient.ts only
 * calls this for a 5xx/network failure, never a 4xx, which is what keeps a
 * gate failure (an expected 4xx) out of GlitchTip without a separate
 * classifier to get wrong.
 */
export function captureApiFailure(requestId: string, path: string, error: Error): void {
  Sentry.captureException(error, {
    tags: { surface: 'gallery', request_id: requestId },
    extra: { path },
  })
}
