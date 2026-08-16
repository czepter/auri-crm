/**
 * G-09: the lightbox's guest identity and the two calls its favorite toggle
 * makes.
 *
 * A gallery guest holds no account, and none of G-06's three gate claims
 * reach this app anyway — they live in apps/studio's portal-surface session,
 * a different origin from this one, so this browser never sees that cookie.
 * A cookie minted by apps/studio instead would be cross-site from here,
 * which only travels credentialed (`SameSite=None; Secure`, CORS with
 * `Access-Control-Allow-Credentials`) — real machinery neither app has yet,
 * for an identifier that protects nothing worth protecting. So identity is
 * generated right here instead: a random id, persisted in this browser's own
 * localStorage, sent as a plain request field with every favorite call. See
 * apps/studio's App\Gallery\Http\Controllers\AssetFavoriteController for the
 * server side of that same trust call.
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

function favoritesPath(versionPublicId: string, key?: string): string {
  const base = `/api/gallery-versions/${versionPublicId}/favorites`

  return key ? `${base}/${key.split('/').map(encodeURIComponent).join('/')}` : base
}

/** Every key this guest has already favorited within one gallery version. */
export async function fetchFavorites(apiBase: string, versionPublicId: string, store?: GuestIdStore): Promise<Set<string>> {
  try {
    const response = await fetch(`${apiBase}${favoritesPath(versionPublicId)}?guest_id=${encodeURIComponent(guestId(store))}`)
    if (!response.ok) return new Set()

    const body: { favorited: string[] } = await response.json()

    return new Set(body.favorited)
  } catch {
    return new Set()
  }
}

/** Flips one asset's favorite mark for this guest; resolves to the state it now holds. */
export async function toggleFavorite(apiBase: string, versionPublicId: string, key: string, store?: GuestIdStore): Promise<boolean> {
  const response = await fetch(`${apiBase}${favoritesPath(versionPublicId, key)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ guest_id: guestId(store) }),
  })

  const body: { favorited: boolean } = await response.json()

  return body.favorited
}
