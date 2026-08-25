/**
 * The widths and formats the image proxy takes, kept by hand a second time.
 * GalleryImageController answers 422 for anything outside
 * App\Gallery\Support\ImageVariant's list, so a drifting copy shows up as an
 * empty tile and nowhere else. apps/gallery keeps its own third copy for the
 * cross-origin viewer, and its docblock says why that is cheaper than a
 * shared package.
 */
export const WIDTHS = [400, 800, 1200, 1600, 2400] as const
export const FORMATS = ['avif', 'webp', 'jpeg'] as const

export type Format = (typeof FORMATS)[number]

/** The grid is at most four columns of a 1440px page. 2400 would only ever be discarded. */
export const GRID_WIDTHS = [400, 800, 1200, 1600] as const

/** The stage fills 72vw, where a retina 2400 pays for itself and 400 never shows. */
export const STAGE_WIDTHS = [800, 1200, 1600, 2400] as const

/**
 * The portal is same-origin: GalleryPasswordController::assets() hands the
 * path over ready (`/galerie-bilder/{version}/{key}`) and only the query is
 * built here.
 */
export function srcSet(src: string, format: Format, widths: readonly number[]): string {
  return widths.map((w) => `${src}?w=${w}&fmt=${format} ${w}w`).join(', ')
}
