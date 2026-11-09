/**
 * G-02 (#230): what the bar over the grid offers, and what each entry shows.
 *
 * One row for both audiences, because both have selections — a client's are
 * named GalleryLists they own across devices, a guest's are the hearts their
 * own session carries. `Alle` is the only entry with no keys behind it: null
 * means "do not filter", which is not the same as an empty selection.
 */
export type Selection = { id: string; label: string; keys: string[] | null }

export type SelectionList = { listId: string; name: string | null; keys: string[] }

export const ALL = 'alle'
export const FAVOURITES = 'favoriten'

export function selections(
  lists: SelectionList[] | null,
  favorited: string[] | null,
  labels: { all: string; heart: string },
): Selection[] {
  const all: Selection = { id: ALL, label: labels.all, keys: null }

  if (lists !== null) {
    return [
      all,
      ...lists.map((list) => ({
        id: list.listId,
        label: list.name ?? labels.heart,
        keys: list.keys,
      })),
    ]
  }

  return favorited === null
    ? [all]
    : [all, { id: FAVOURITES, label: labels.heart, keys: favorited }]
}

export function countOf(selection: Selection, total: number): number {
  return selection.keys?.length ?? total
}

export function visible<T extends { key: string }>(assets: T[], keys: string[] | null): T[] {
  return keys === null ? assets : assets.filter((asset) => keys.includes(asset.key))
}

export const HIDDEN = 'ausgeblendet'

/** Ausgeblendetes verschwindet aus dem Raster und bleibt über einen eigenen Eintrag erreichbar. */
export function withHidden(bar: Selection[], keys: string[], label: string): Selection[] {
  return keys.length ? [...bar, { id: HIDDEN, label, keys }] : bar
}
