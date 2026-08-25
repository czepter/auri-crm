import { callApi } from './apiClient'

/**
 * R39/R40: the one gate [galleryId].astro asks before it will render a
 * single picture, a count or a cover — the read response says so itself
 * (`prerelease: true`), rather than the page inferring it from an absent
 * `version` key. A gallery object that fails this must never be read past
 * `.title`/`.public_id`.
 */
export function isPrerelease(gallery: { prerelease?: boolean } | null): boolean {
  return gallery !== null && gallery.prerelease === true
}

export type PrereleaseSignupResult = 'accepted' | 'refused'

/**
 * R40/R41/R44: the pre-release page's one call.
 *
 * The API answers 202 whether the address is new, already on the list, or
 * the gallery is not even in pre-release (GalleryPrereleaseSignupController's
 * own doc comment) — R41's uniform response, unchanged. A 429 is different:
 * R44 ruled that a refused request (rate limited, a filled honeypot, too
 * fast) must not be confirmed as if it had been recorded, so this reports
 * that distinction back rather than collapsing every response into `true`
 * the way the previous version did.
 *
 * `website`/`elapsed` mirror PublicInquiryController's own honeypot and
 * timing floor — see the controller's own doc comment for why this page,
 * which ships no client JavaScript, computes `elapsed` from a hidden
 * render-time field instead of an inline script.
 */
export async function signUpForPrerelease(
  apiBase: string,
  galleryId: string,
  requestId: string,
  email: string,
  website: string,
  elapsed: number | null,
): Promise<PrereleaseSignupResult> {
  const response = await callApi(apiBase, `/api/galleries/${galleryId}/benachrichtigung`, requestId, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, website, elapsed }),
  })

  return response.status === 202 ? 'accepted' : 'refused'
}
