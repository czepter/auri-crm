export type IntroRow = 'favorite' | 'comment'

export const INTRO_TEXT: Record<IntroRow, string> = {
  favorite: 'Favorit markieren',
  comment: 'Kommentar schreiben',
}

/** Gäste blenden nichts aus; es bleiben die Zeilen, die das Studio erlaubt. */
export function introRows(permissions: { favorites: boolean; comments: boolean }): IntroRow[] {
  const rows: IntroRow[] = []

  if (permissions.favorites) rows.push('favorite')
  if (permissions.comments) rows.push('comment')

  return rows
}

const key = (ref: string) => `gallery-intro:${ref}`

/** Einmal pro Browser; ohne Speicher erscheint sie nicht bei jedem Aufruf. */
export function introPending(ref: string, enabled: boolean, rows: IntroRow[], store: Pick<Storage, 'getItem'> | null): boolean {
  if (!enabled || rows.length === 0 || store === null) return false

  try {
    return store.getItem(key(ref)) === null
  } catch {
    return false
  }
}

export function markIntroSeen(ref: string, store: Pick<Storage, 'setItem'> | null): void {
  try {
    store?.setItem(key(ref), '1')
  } catch {
    // Speicher gesperrt: die Einführung kommt dann nicht wieder.
  }
}

/** Das Dokument ruft das auf; localStorage selbst kann schon beim Lesen der Eigenschaft werfen. */
export function browserStorage(): Storage | null {
  try {
    return window.localStorage
  } catch {
    return null
  }
}
