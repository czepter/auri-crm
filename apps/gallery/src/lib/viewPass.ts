import { callApi } from './apiClient'

/**
 * G-06's view claim, on this side of the origin boundary.
 *
 * The portal keeps the claim in its own session. Nothing of that reaches
 * here — this is a separate process on a separate host — so what this viewer
 * holds is a signed pass the api surface issued when the guest got the
 * password right (App\Gallery\Support\GalleryViewPass). It goes back as a
 * bearer on every call.
 *
 * Kept in a cookie rather than localStorage because the page that needs it
 * is rendered on the server: a value only the browser can read would mean
 * shipping the grid as client-side JS, which is the one thing this viewer
 * exists not to do.
 *
 * HttpOnly, so no script on this origin can read it; SameSite=Lax, so it
 * rides an ordinary navigation and not a cross-site request; Secure
 * everywhere but a plain-http dev host.
 */
const COOKIE_PREFIX = 'gallery_pass_'

export function passCookieName(galleryId: string): string {
  return COOKIE_PREFIX + galleryId
}

/** Twelve hours, matching GalleryViewPass::VALID_FOR_HOURS — a cookie that outlives its pass is a broken gate screen. */
export const PASS_MAX_AGE_SECONDS = 12 * 60 * 60

/**
 * Buys a pass with a password, or without one for a gallery that carries no
 * gate. Returns null when the password is wrong — and says nothing about
 * whether the gallery exists, same rule the api answers with.
 */
export async function buyPass(
  apiBase: string,
  galleryId: string,
  requestId: string,
  password?: string,
): Promise<string | null> {
  const response = await callApi(apiBase, `/api/galleries/${galleryId}/passwort`, requestId, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(password === undefined ? {} : { password }),
  })

  if (!response.ok) {
    return null
  }

  return ((await response.json()) as { pass: string }).pass
}
