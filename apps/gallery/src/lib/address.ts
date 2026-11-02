import { callApi } from './apiClient'

/** How the api addresses a gallery by `/{prefix}/{slug}`, see Gallery::findByReference(). */
export function galleryRef(prefix: string, slug: string): string {
  return `${prefix}~${slug}`
}

/**
 * Sends a link from before prefixes (`/g/{slug}`, `/{public_id}`) to its
 * current address, query kept (`?preview=`, `?bestaetigung=`). Unknown, or
 * an old slug shared by two galleries: 404.
 */
export async function legacyRedirect(
  apiBase: string,
  reference: string,
  requestId: string,
  url: URL,
  suffix = '',
): Promise<Response> {
  const response = await callApi(apiBase, `/api/gallery-address/${encodeURIComponent(reference)}`, requestId, {
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    return new Response(null, { status: 404 })
  }

  const { path } = (await response.json()) as { path: string }

  return new Response(null, { status: 302, headers: { Location: `/${path}${suffix}${url.search}` } })
}
