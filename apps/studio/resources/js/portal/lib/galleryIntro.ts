export type IntroRow = 'favorite' | 'comment' | 'hide'

export function introRows(offers: { lists: boolean; comments: boolean; hide: boolean }): IntroRow[] {
  const rows: IntroRow[] = []

  if (offers.lists) rows.push('favorite')
  if (offers.comments) rows.push('comment')
  if (offers.hide) rows.push('hide')

  return rows
}

const key = (galleryId: string) => `gallery-intro:${galleryId}`

function storage(): Storage | null {
  try {
    return globalThis.localStorage ?? null
  } catch {
    return null
  }
}

/** Einmal pro Browser; ohne Speicher erscheint sie nicht bei jedem Aufruf. */
export function introPending(galleryId: string, enabled: boolean, rows: IntroRow[], store: Storage | null = storage()): boolean {
  if (!enabled || rows.length === 0 || store === null) return false

  try {
    return store.getItem(key(galleryId)) === null
  } catch {
    return false
  }
}

export function markIntroSeen(galleryId: string, store: Storage | null = storage()): void {
  try {
    store?.setItem(key(galleryId), '1')
  } catch {
    // Speicher gesperrt: die Einführung kommt dann nicht wieder.
  }
}
