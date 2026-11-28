import type { RequestEventData } from '@sentry/node'

/** Sentry's own `Extras` (`@sentry/core`) is `Record<string, unknown>` under the hood; not re-exported from `@sentry/node` itself. */
type Extras = Record<string, unknown>

/**
 * G-13's `before_send` hook — the Astro-side mirror of apps/studio's
 * App\Support\Observability\ScrubSensitiveData (config/sentry.php), which
 * this deliberately matches header-for-header and field-for-field rather
 * than inventing its own scrub vocabulary. Load-bearing here in a way it
 * isn't purely for hygiene on the Laravel side: this SSR process is a
 * confused deputy (issue G-13's own framing) — it forwards a guest's own
 * token and carries a read-only metadata key of its own, so an unscrubbed
 * event from this process is where both would leak.
 *
 * A pure function over a plain event object, not a class wired only through
 * Sentry.init — the same reason ScrubSensitiveData is tested directly
 * against a constructed Event rather than through a live capture.
 */
const REDACTED = '[Filtered]'

/** Header names redacted wherever they appear on a captured request. */
const SENSITIVE_HEADERS = [
  'authorization', // carries the guest's own bearer token (G-01's Sanctum guest token), forwarded on this SSR server's behalf
  'cookie',
  'x-guest-pass',
  'x-download-pass',
  'x-service-key', // this SSR process's own read-only metadata credential — never a guest's, but no less a leak
  'x-guest-token',
]

/** Body-field names redacted wherever they appear, recursively, in request data. */
const SENSITIVE_FIELDS = ['password', 'gallery_password', 'pin', 'gallery_pin', 'guest_token', 'service_key']

/**
 * Generic over the event type (rather than pinned to Sentry's `ErrorEvent`,
 * whose `type: undefined` discriminant only exists to distinguish it from a
 * transaction/profile/replay/feedback event) so this stays exactly what
 * `Sentry.init`'s `beforeSend` is typed to receive and return — including
 * from Sentry.init itself, where the inferred type is `ErrorEvent` — without
 * a cast at either call site.
 */
export function scrubEvent<T extends { request?: RequestEventData; extra?: Extras }>(event: T): T {
  const request = event.request

  if (request) {
    // Cookies never survive at all — there is no partial-redaction shape for
    // them worth keeping, same call apps/studio's ScrubSensitiveData makes.
    const { cookies: _cookies, ...rest } = request

    event.request = {
      ...rest,
      headers: request.headers ? scrubHeaders(request.headers) : request.headers,
      url: typeof request.url === 'string' ? scrubSignedUrl(request.url) : request.url,
      query_string: typeof request.query_string === 'string' ? scrubSignedUrl(request.query_string) : request.query_string,
      data: request.data && typeof request.data === 'object' ? scrubFields(request.data as Record<string, unknown>) : request.data,
    }
  }

  if (event.extra) {
    event.extra = scrubFields(event.extra as Record<string, unknown>)
  }

  return event
}

function scrubHeaders(headers: Record<string, string>): Record<string, string> {
  const scrubbed: Record<string, string> = {}

  for (const [name, value] of Object.entries(headers)) {
    scrubbed[name] = SENSITIVE_HEADERS.includes(name.toLowerCase()) ? REDACTED : value
  }

  return scrubbed
}

/**
 * Laravel signed URLs (URL::signedRoute) carry the proof in `signature`; mirrors ScrubSensitiveData::scrubSignature exactly.
 * Der Link aus der Download-Mail trägt es als Pfadsegment (`/download/{token}`, api: `/download-mail/{token}`).
 */
function scrubSignedUrl(value: string): string {
  return value
    .replace(/([?&])(signature|expires)=[^&]*/gi, `$1$2=${REDACTED}`)
    .replace(/(\/download(?:-mail)?\/)[0-9a-f]{64}(?=[/?#]|$)/gi, `$1${REDACTED}`)
}

function scrubFields(data: Record<string, unknown>): Record<string, unknown> {
  const scrubbed: Record<string, unknown> = {}

  for (const [key, value] of Object.entries(data)) {
    if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
      scrubbed[key] = scrubFields(value as Record<string, unknown>)
      continue
    }

    scrubbed[key] = SENSITIVE_FIELDS.includes(key.toLowerCase()) ? REDACTED : value
  }

  return scrubbed
}
