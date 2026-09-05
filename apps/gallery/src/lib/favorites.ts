/**
 * G-09: the lightbox's two favorite calls.
 *
 * Die Kennung kommt nicht mehr von hier. Ein Herz braucht seit der
 * Gast-Identität einen Absender — den signierten Pass aus guest.ts, den der
 * Server nach einer bestätigten Adresse ausgestellt hat. Ohne ihn antwortet
 * die api 403, und das ist der Punkt: eine Markierung ohne Absender wäre auf
 * dem nächsten Gerät verloren.
 *
 * Die zufällige UUID unten lebt weiter, aber nur noch für eine Sache: was
 * jemand vor dem Anmelden markiert hat, wandert beim Bestätigen auf seine
 * Kennung (ConfirmGalleryGuest::adopt).
 *
 * G-05 (#242): the view claim is a different matter and does travel — as a
 * signed pass the api surface issued for this gallery, minted server-side
 * and handed to this module by the page. Every call carries it, because the
 * api gates all four gallery routes on it.
 */

const STORAGE_KEY = 'gallery_guest_id'

export interface GuestIdStore {
  getItem(key: string): string | null
  setItem(key: string, value: string): void
}

/** The durable id this browser favorites under — minted once, reused after. */
export function guestId(store: GuestIdStore = localStorage): string {
  const existing = store.getItem(STORAGE_KEY)
  if (existing) return existing

  const minted = crypto.randomUUID()
  store.setItem(STORAGE_KEY, minted)

  return minted
}

/** G-05 (#242): the view claim, as a bearer. Absent for a gallery with no gate. */
export function passHeader(pass?: string | null): Record<string, string> {
  return pass ? { Authorization: `Bearer ${pass}` } : {}
}

function favoritesPath(versionPublicId: string, key?: string): string {
  const base = `/api/gallery-versions/${versionPublicId}/favorites`

  return key ? `${base}/${key.split('/').map(encodeURIComponent).join('/')}` : base
}

/** Every key this guest has already favorited within one gallery version. */
export async function fetchFavorites(
  apiBase: string,
  versionPublicId: string,
  pass?: string | null,
  guestPass?: string | null,
): Promise<Set<string>> {
  if (!guestPass) return new Set()

  try {
    const response = await fetch(
      `${apiBase}${favoritesPath(versionPublicId)}?guest_pass=${encodeURIComponent(guestPass)}`,
      { headers: passHeader(pass) },
    )
    if (!response.ok) return new Set()

    const body: { favorited: string[] } = await response.json()

    return new Set(body.favorited)
  } catch {
    return new Set()
  }
}

/** Flips one asset's favorite mark for this guest; resolves to the state it now holds. */
export async function toggleFavorite(
  apiBase: string,
  versionPublicId: string,
  key: string,
  pass?: string | null,
  guestPass?: string | null,
): Promise<boolean> {
  const response = await fetch(`${apiBase}${favoritesPath(versionPublicId, key)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...passHeader(pass) },
    body: JSON.stringify({ guest_pass: guestPass }),
  })

  const body: { favorited: boolean } = await response.json()

  return body.favorited
}
