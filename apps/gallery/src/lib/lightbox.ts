import { imageUrl, srcSet, type GalleryAsset } from './images'
import { fetchFavorites, toggleFavorite } from './favorites'
import { fetchComments, postComment, type GalleryComment } from './comments'

/**
 * G-09/G-12: wires up the lightbox markup `[galleryId].astro` renders
 * (matching mockup 10) to the grid it opens from. Astro ships no client JS
 * by default — this is the one hand-written module the gallery viewer
 * loads, no framework, matching apps/gallery's existing pattern
 * (package.json carries none).
 */
export interface LightboxData {
  assets: GalleryAsset[]
  apiBase: string
  // Already-built base path and query gate for the image bytes — see
  // images.ts. A published version and R31's draft preview address bytes
  // under different routes with different credentials; this module only
  // ever needs the result.
  imageBase: string
  gate: string
  // R31: a draft has no GalleryVersion to favorite or comment against —
  // AssetFavoriteController/AssetCommentController are both version-scoped
  // — so a draft preview wires image navigation only. The markup those two
  // features would occupy is absent from the draft page entirely
  // ([galleryId].astro), not merely hidden, so every selector below is
  // optional-chained rather than assumed present. null exactly when
  // interactive is false.
  versionPublicId: string | null
  // G-05 (#242): the view claim this page was served under, for the
  // favorites/comments calls specifically. Null for a gallery with no gate.
  pass: string | null
}

const STAGE_WIDTH = 1600

export type KeyIntent = 'close' | 'prev' | 'next' | null

/**
 * The comment field owns the arrows while it holds focus — a guest moving the
 * caret back over a typo would otherwise flip to the next picture. Escape
 * closes regardless.
 */
export function keyIntent(key: string, typing: boolean): KeyIntent {
  if (key === 'Escape') return 'close'
  if (typing) return null
  if (key === 'ArrowLeft') return 'prev'
  if (key === 'ArrowRight') return 'next'

  return null
}

/** Both ends wrap: the last picture's next is the first. */
export function stepIndex(index: number, delta: number, length: number): number {
  return (index + delta + length) % length
}

export function initLightbox({ assets, apiBase, imageBase, gate, versionPublicId, pass }: LightboxData): void {
  const interactive = versionPublicId !== null
  const root = document.getElementById('lightbox')
  if (!root || assets.length === 0) return

  const img = root.querySelector<HTMLImageElement>('[data-lb-img]')
  const pos = root.querySelector<HTMLElement>('[data-lb-pos]')
  const favBtn = root.querySelector<HTMLButtonElement>('[data-lb-favorite]')
  const favLabel = root.querySelector<HTMLElement>('[data-lb-favorite-label]')
  const thumbs = Array.from(root.querySelectorAll<HTMLAnchorElement>('[data-lb-thumb]'))
  const commentsList = root.querySelector<HTMLElement>('[data-lb-comments]')
  const commentInput = root.querySelector<HTMLInputElement>('[data-lb-comment-input]')
  if (!img || !pos) return

  let index = 0
  let favorited = new Set<string>()

  const render = (): void => {
    const asset = assets[index]
    img.src = imageUrl(apiBase, imageBase, asset.key, STAGE_WIDTH, 'jpeg', gate)
    img.srcset = srcSet(apiBase, imageBase, asset.key, 'jpeg', gate)
    pos.textContent = `${index + 1} von ${assets.length}`
    thumbs.forEach((a, i) => a.classList.toggle('on', i === index))
    if (interactive) {
      renderFavorite()
      loadComments()
    }
  }

  const tileHearts = new Map<number, HTMLButtonElement>()

  const renderFavorite = (): void => {
    if (!favBtn || !favLabel) return
    const on = favorited.has(assets[index].key)
    favBtn.classList.toggle('on', on)
    favLabel.textContent = on ? 'Favorit entfernen' : 'Favorit'
    renderTileHearts()
  }

  /** The same mark, on the wall — a guest should see what they picked without opening each one. */
  const renderTileHearts = (): void => {
    tileHearts.forEach((button, i) => button.classList.toggle('on', favorited.has(assets[i].key)))
  }

  const flip = (i: number): void => {
    // Only ever called while interactive (favBtn click, tile heart click —
    // both wired inside `if (interactive)` below), so versionPublicId is set.
    void toggleFavorite(apiBase, versionPublicId as string, assets[i].key, pass).then((on) => {
      const key = assets[i].key
      if (on) favorited.add(key)
      else favorited.delete(key)
      renderFavorite()
    })
  }

  // Mockup galerie-lightbox.html: a name and a time above every line. The
  // api names a signed-in client and answers "Gast" for everyone else — on
  // this viewer that is always everyone, since no account reaches it.
  const renderComments = (comments: GalleryComment[]): void => {
    if (!commentsList) return

    commentsList.replaceChildren(
      ...comments.map((comment) => {
        const row = document.createElement('div')
        row.className = comment.own ? 'cmt' : 'cmt anon'

        const who = document.createElement('div')
        who.className = 'who'
        const author = document.createElement('b')
        author.textContent = comment.author
        const time = document.createElement('span')
        time.textContent = new Date(comment.createdAt).toLocaleString('de-DE')
        who.append(author, time)

        const text = document.createElement('p')
        text.textContent = comment.body

        row.append(who, text)

        return row
      }),
    )
  }

  // G-12: keyed to the asset a slower request was fetching for, so a fetch
  // that resolves after the guest has already stepped to a different image
  // never overwrites that image's own list with a stale one.
  const loadComments = (): void => {
    const key = assets[index].key
    void fetchComments(apiBase, versionPublicId as string, key, pass).then((comments) => {
      if (assets[index].key === key) renderComments(comments)
    })
  }

  const open = (i: number): void => {
    index = i
    // postComment reads the key at send time, so a draft that outlives the
    // picture it was typed under would file against the next one.
    if (commentInput) commentInput.value = ''
    render()
    root.classList.add('open')
    document.body.style.overflow = 'hidden'
  }

  const close = (): void => {
    root.classList.remove('open')
    document.body.style.overflow = ''
  }

  const step = (delta: number): void => open(stepIndex(index, delta, assets.length))

  document.querySelectorAll<HTMLElement>('.tile[data-lb-open]').forEach((tile) => {
    const i = Number(tile.dataset.index)

    tile.addEventListener('click', (event) => {
      event.preventDefault()
      if (Number.isInteger(i)) open(i)
    })

    if (!interactive) return

    const heart = tile.querySelector<HTMLButtonElement>('[data-fav]')

    if (heart && Number.isInteger(i)) {
      tileHearts.set(i, heart)
      heart.addEventListener('click', (event) => {
        // The tile is a link to the detail view; the heart on it is not.
        event.preventDefault()
        event.stopPropagation()
        flip(i)
      })
    }
  })

  thumbs.forEach((a, i) => {
    a.addEventListener('click', (event) => {
      event.preventDefault()
      open(i)
    })
  })

  root.querySelectorAll<HTMLElement>('[data-lb-close]').forEach((btn) => btn.addEventListener('click', close))
  root.querySelector('[data-lb-prev]')?.addEventListener('click', () => step(-1))
  root.querySelector('[data-lb-next]')?.addEventListener('click', () => step(1))

  if (interactive) {
    favBtn?.addEventListener('click', () => flip(index))

    commentInput?.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return
      const body = commentInput.value.trim()
      if (body === '') return

      commentInput.disabled = true
      void postComment(apiBase, versionPublicId as string, assets[index].key, body, pass)
        .then((comment) => {
          if (comment) {
            commentInput.value = ''
            loadComments()
          }
        })
        .finally(() => {
          commentInput.disabled = false
        })
    })

    void fetchFavorites(apiBase, versionPublicId as string, pass).then((keys) => {
      favorited = keys
      renderTileHearts()
      if (root.classList.contains('open')) renderFavorite()
    })
  }

  document.addEventListener('keydown', (event) => {
    if (!root.classList.contains('open')) return

    const intent = keyIntent(event.key, document.activeElement === commentInput)

    if (intent === 'close') close()
    if (intent === 'prev') step(-1)
    if (intent === 'next') step(1)
  })
}
