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

/**
 * R40/R41: the pre-release page's one call. Always resolves — the api
 * surface answers 202 whether the address is new, already on the list, or
 * the gallery is not even in pre-release (GalleryPrereleaseSignupController's
 * own doc comment) — so this has nothing to report back beyond "the request
 * went out", which is all the page needs to move from the form to its
 * confirmation.
 */
export async function signUpForPrerelease(apiBase: string, galleryId: string, requestId: string, email: string): Promise<boolean> {
  const response = await callApi(apiBase, `/api/galleries/${galleryId}/benachrichtigung`, requestId, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email }),
  })

  return response.ok
}
