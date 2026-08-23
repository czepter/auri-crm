export interface SetRow {
  id: string
}

/**
 * R22: Alle only means something once a second set exists. Every gallery is
 * born with exactly one (Gallery::booted()), so the pictures pane starts on
 * that set rather than on Alle — GalleryRail hides its whole row list (Alle
 * included) while there is only one to choose from, so Alle would otherwise
 * be unreachable and nothing could ever emit a real set id.
 */
export function initialActiveSetId(sets: SetRow[]): string | null {
  return sets.length === 1 ? sets[0].id : null
}

/**
 * Re-applies the same invariant after sets change. Deleting down to one
 * collapses Alle (or whatever set just vanished) into the survivor; with
 * more than one left, only a vanished current set falls back to Alle.
 */
export function nextActiveSetId(sets: SetRow[], current: string | null): string | null {
  if (sets.length === 1) {
    return sets[0].id
  }

  if (current !== null && !sets.some((row) => row.id === current)) {
    return null
  }

  return current
}
