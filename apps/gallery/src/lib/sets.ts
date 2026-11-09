import type { GalleryAsset } from './images'

export interface GuestSet {
  id: string
  name: string
}

/** `?set=` nennt ein Set; unbekannt oder nicht für Gäste ist ein eigener Fall, kein leeres Raster. */
export function pickSet(sets: GuestSet[], id: string | null): { set: GuestSet | null; missing: boolean } {
  if (id === null || id === '') return { set: null, missing: false }

  const set = sets.find((candidate) => candidate.id === id) ?? null

  return { set, missing: set === null }
}

export function assetsOf(assets: GalleryAsset[], set: GuestSet | null): GalleryAsset[] {
  return set === null ? assets : assets.filter((asset) => asset.set === set.name)
}
