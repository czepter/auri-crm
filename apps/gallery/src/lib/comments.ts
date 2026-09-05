import { passHeader } from './favorites'

/**
 * G-12: the lightbox's comment rail — mockup 10's `.cmts`/`.lbwrite`, cut
 * from G-09 to this story.
 *
 * Der Absender steht seit der Gast-Identität fest: der signierte Pass aus
 * guest.ts weist ihn aus, und die api schickt den Namen zurück, den er beim
 * Bestätigen hinterlegt hat. Die Adresse bleibt dabei auf dem Server —
 * sichtbar ist der Name.
 */
export interface GalleryComment {
  body: string
  /** G-01 (#229): a signed-in client's own name, or "Gast". Always the latter here. */
  author: string
  own: boolean
  createdAt: string
}

function commentsPath(versionPublicId: string): string {
  return `/api/gallery-versions/${versionPublicId}/comments`
}

/** Every comment left on one asset, oldest first. */
export async function fetchComments(
  apiBase: string,
  versionPublicId: string,
  key: string,
  pass?: string | null,
): Promise<GalleryComment[]> {
  try {
    const response = await fetch(
      `${apiBase}${commentsPath(versionPublicId)}?asset_key=${encodeURIComponent(key)}`,
      { headers: passHeader(pass) },
    )
    if (!response.ok) return []

    const body: { comments: { body: string; author?: string; own?: boolean; created_at: string }[] } =
      await response.json()

    return body.comments.map((comment) => ({
      body: comment.body,
      author: comment.author ?? 'Gast',
      own: comment.own ?? false,
      createdAt: comment.created_at,
    }))
  } catch {
    return []
  }
}

/** Posts one comment as this guest; resolves to it as stored, or null on failure. */
export async function postComment(
  apiBase: string,
  versionPublicId: string,
  key: string,
  body: string,
  pass?: string | null,
  guestPass?: string | null,
): Promise<GalleryComment | null> {
  if (!guestPass) return null

  try {
    const response = await fetch(`${apiBase}${commentsPath(versionPublicId)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...passHeader(pass) },
      body: JSON.stringify({ guest_pass: guestPass, asset_key: key, body }),
    })
    if (!response.ok) return null

    const json: { comment: { body: string; author?: string; own?: boolean; created_at: string } } =
      await response.json()

    return {
      body: json.comment.body,
      author: json.comment.author ?? 'Gast',
      own: json.comment.own ?? false,
      createdAt: json.comment.created_at,
    }
  } catch {
    return null
  }
}
