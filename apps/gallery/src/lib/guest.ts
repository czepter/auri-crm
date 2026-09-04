import { callApi } from './apiClient'

/**
 * Wer der Gast ist.
 *
 * Bis hierher war das eine zufällige UUID im localStorage (favorites.ts):
 * Gerätewechsel löschte jede Markierung, und unter jedem Kommentar stand
 * „Gast". Jetzt ist es eine bestätigte Adresse — dieselbe Adresse ergibt auf
 * jedem Gerät dieselbe Kennung, und der Verlauf taucht wieder auf.
 *
 * Der Pass reist wie der Ansichtspass: signiert vom Server, hier in einem
 * Cookie, als Feld zurück. Ein Cookie und nicht localStorage, weil die Seite
 * serverseitig gerendert wird — ein Wert, den nur der Browser lesen kann,
 * hieße das Raster als Skript auszuliefern, und genau dafür gibt es diesen
 * Betrachter nicht.
 */
const COOKIE_PREFIX = 'gallery_guest_'

export function guestCookieName(galleryId: string): string {
  return COOKIE_PREFIX + galleryId
}

/** Solange der Pass gilt (GalleryGuestPass::VALID_FOR_DAYS). */
export const GUEST_MAX_AGE_SECONDS = 90 * 24 * 60 * 60

export interface GuestIdentity {
  name: string
  email: string
  pass: string
}

/**
 * Ein Namensvorschlag aus dem Teil vor dem @.
 *
 * `anna.mueller1987@example.com` wird `Anna Mueller`. Punkte, Unterstriche
 * und Bindestriche trennen Wörter, angehängte Ziffern fallen weg — es ist
 * ein Vorschlag im Feld, kein Urteil, und wer anders heißt, tippt darüber.
 */
export function nameFromEmail(email: string): string {
  const local = email.split('@')[0] ?? ''

  return local
    .split(/[._+-]+/)
    .map((part) => part.replace(/\d+$/, ''))
    .filter((part) => part !== '')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

/** Fordert die sechs Ziffern an. false heißt: zu oft in kurzer Zeit. */
export async function requestCode(
  apiBase: string,
  galleryId: string,
  requestId: string,
  email: string,
  name: string,
  pass?: string | null,
): Promise<boolean> {
  try {
    const response = await callApi(
      apiBase,
      `/api/galleries/${galleryId}/guest`,
      requestId,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, name }),
      },
      pass,
    )

    return response.ok
  } catch {
    return false
  }
}

/**
 * Löst Code oder Link ein. `anonymousId` reist mit, damit die Markierungen
 * dieses Browsers dem bestätigten Gast zufallen statt verloren zu gehen.
 */
export async function confirm(
  apiBase: string,
  galleryId: string,
  requestId: string,
  payload: { email?: string; code?: string; token?: string; anonymousId?: string },
  pass?: string | null,
): Promise<GuestIdentity | null> {
  try {
    const response = await callApi(
      apiBase,
      `/api/galleries/${galleryId}/guest/bestaetigung`,
      requestId,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: payload.email,
          code: payload.code,
          token: payload.token,
          anonymous_id: payload.anonymousId,
        }),
      },
      pass,
    )

    if (!response.ok) return null

    const body: { confirmed: boolean; name: string; email: string; pass: string } = await response.json()

    return body.confirmed ? { name: body.name, email: body.email, pass: body.pass } : null
  } catch {
    return null
  }
}
