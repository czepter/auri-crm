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
  width: number | null
  height: number | null
}

/** One image-proxy URL — never a bucket URL, per G-01a's delivery rule. */
export function imageUrl(apiBase: string, versionPublicId: string, key: string, width: number, format: Format): string {
  const path = key.split('/').map(encodeURIComponent).join('/')

  return `${apiBase}/api/gallery-versions/${versionPublicId}/images/${path}?w=${width}&fmt=${format}`
}

/** The srcset attribute for one format, across every allowed width. */
export function srcSet(apiBase: string, versionPublicId: string, key: string, format: Format): string {
  return WIDTHS.map((w) => `${imageUrl(apiBase, versionPublicId, key, w, format)} ${w}w`).join(', ')
}
