import { guestId, passHeader, type GuestIdStore } from './favorites'

/**
 * G-12: the lightbox's comment rail — mockup 10's `.cmts`/`.lbwrite`, cut
 * from G-09 to this story. Same guest identity as favorites.ts (see that
 * module's own doc comment): no account behind it, so a comment carries no
 * name — the lightbox renders every one as "Gast" rather than inventing an
 * identity this app never collects.
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
  store?: GuestIdStore,
): Promise<GalleryComment | null> {
  try {
    const response = await fetch(`${apiBase}${commentsPath(versionPublicId)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...passHeader(pass) },
      body: JSON.stringify({ guest_id: guestId(store), asset_key: key, body }),
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
