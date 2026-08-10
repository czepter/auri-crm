import { randomUUID } from 'node:crypto'

/**
 * G-13's Astro-side half of F-11's one `X-Request-Id` per request — mirrors
 * apps/studio's App\Http\Middleware\AssignRequestId exactly: accept the
 * edge's id when present, mint one otherwise. Called once per request from
 * src/middleware.ts, which stores the result on `Astro.locals.requestId` and
 * echoes it back on the response the same way the Laravel middleware does.
 */
export function resolveRequestId(header: string | null | undefined): string {
  return header && header.trim() !== '' ? header : randomUUID()
}
