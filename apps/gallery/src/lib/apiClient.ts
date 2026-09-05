import { captureApiFailure } from './observability/sentry'

// ponytail: fixed rather than configurable — revisit if a route ever needs a
// different budget than the SSR page render itself tolerates.
const TIMEOUT_MS = 10_000

/**
 * G-13: the one place apps/gallery's SSR process calls the `api` surface —
 * every call this Astro server makes to Laravel goes through here, so the
 * `X-Request-Id` propagation and outage reporting the story asks for exist
 * once, not per call site.
 *
 * Forwards the request id this SSR request is already carrying
 * (src/middleware.ts) so the api-surface request Laravel receives, and this
 * SSR request that triggered it, are findable as one incident by the same
 * id (F-11's AssignRequestId echoes back whatever id it's sent, it never
 * mints a second one for a caller that already supplied one).
 *
 * Reports a 5xx or a network failure/timeout as a gallery outage — this is
 * the crux of "the gallery is the surface the studio hears about last" from
 * the guest's side, even though Laravel may already have logged the same
 * incident on its own. A 4xx (not found, unauthenticated, a wrong gallery
 * password or PIN once a gate ever calls through here) is never reported:
 * it's expected traffic, not an outage, and this is the only rule that
 * needs to exist for that — no separate gate-failure classifier to keep in
 * sync with G-06/G-07's own definition of one.
 */
export async function callApi(
  apiBase: string,
  path: string,
  requestId: string,
  init: RequestInit = {},
  // G-06's view claim, when this gallery carries one. A bearer rather than a
  // query parameter: a pass in a URL ends up in an access log, a Referer and
  // a bookmark. See src/lib/viewPass.ts.
  pass?: string | null,
): Promise<Response> {
  const headers = new Headers(init.headers)
  headers.set('X-Request-Id', requestId)

  if (pass) {
    headers.set('Authorization', `Bearer ${pass}`)
  }

  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS)

  let response: Response

  try {
    response = await fetch(`${apiBase}${path}`, { ...init, headers, signal: controller.signal })
  } catch (error) {
    // A network failure or the timeout above both read as a broken gallery
    // to the guest, whether or not Laravel ever saw the request at all.
    captureApiFailure(requestId, path, error instanceof Error ? error : new Error(String(error)))

    return new Response(null, { status: 502 })
  } finally {
    clearTimeout(timer)
  }

  if (response.status >= 500) {
    captureApiFailure(requestId, path, new Error(`api ${path} responded ${response.status}`))
  }

  return response
}
