/**
 * G-08: builds the URLs the Astro grid points its <picture> tags at.
 *
 * Widths and formats mirror apps/studio's App\Gallery\Support\ImageVariant
 * allow-list exactly — the image-proxy route (GalleryImageController)
 * rejects anything outside it with a 422, so drifting from that list here
 * would just produce broken tiles. Two small, hand-kept constant arrays
 * beat a generated-and-shared package for this: the alternative (a
 * packages/* module imported by a PHP app and a Node app) is real
 * cross-runtime tooling for four numbers and three strings.
 */
export const WIDTHS = [400, 800, 1200, 1600, 2400] as const
export const FORMATS = ['avif', 'webp', 'jpeg'] as const

export type Format = (typeof FORMATS)[number]

export interface GalleryAsset {
  key: string
  set?: string | null
  width: number | null
  height: number | null
}

/**
 * One image-proxy URL — never a bucket URL, per G-01a's delivery rule.
 *
 * `base` is the path this asset's bytes are addressed under: a published
 * version (`/api/gallery-versions/{id}/images`) or, for R31's draft
 * preview, the gallery's own draft route
 * (`/api/galleries/{id}/entwurf-bilder`, GalleryDraftImageController) — a
 * draft has no GalleryVersion to build the first shape from. Callers
 * compute it once rather than this function branching on a "draft?" flag,
 * because the two routes also carry different query credentials (see
 * `gate`).
 *
 * `gate` is that credential, pre-formatted as a `&key=value` suffix (or
 * empty) — a view-password pass on the published path, a GalleryPreviewPass
 * on the draft one, carried as a query parameter rather than a header
 * because a browser will not attach one to an `<img>`. See
 * App\Http\Middleware\EnsureGalleryPass for what that costs and why it is
 * bounded.
 */
export function imageUrl(apiBase: string, base: string, key: string, width: number, format: Format, gate = ''): string {
  const path = key.split('/').map(encodeURIComponent).join('/')

  return `${apiBase}${base}/${path}?w=${width}&fmt=${format}${gate}`
}

/** The srcset attribute for one format, across every allowed width. */
export function srcSet(apiBase: string, base: string, key: string, format: Format, gate = ''): string {
  return WIDTHS.map((w) => `${imageUrl(apiBase, base, key, w, format, gate)} ${w}w`).join(', ')
}

/** `imageUrl`/`srcSet`'s `base` for a published GalleryVersion. */
export function versionImageBase(versionPublicId: string): string {
  return `/api/gallery-versions/${versionPublicId}/images`
}

/** `imageUrl`/`srcSet`'s `base` for R31's draft preview. */
export function draftImageBase(galleryPublicId: string): string {
  return `/api/galleries/${galleryPublicId}/entwurf-bilder`
}

/** `imageUrl`/`srcSet`'s `gate`, given whichever pass this page is actually holding. */
export function passGate(pass?: string | null): string {
  return pass ? `&pass=${encodeURIComponent(pass)}` : ''
}

/** Same shape as `passGate`, under the query name GalleryDraftImageController and GalleryReadController's draft branch both read. */
export function previewGate(token?: string | null): string {
  return token ? `&preview=${encodeURIComponent(token)}` : ''
}
