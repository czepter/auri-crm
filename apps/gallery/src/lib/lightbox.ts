import { imageUrl, srcSet, type GalleryAsset } from './images'
import { fetchFavorites, toggleFavorite } from './favorites'

/**
 * G-09: wires up the lightbox markup `[galleryId].astro` renders (matching
 * mockup 10 exactly, comments rail cut to G-12) to the grid it opens from.
 * Astro ships no client JS by default — this is the one hand-written module
 * the gallery viewer loads, no framework, matching apps/gallery's existing
 * pattern (package.json carries none).
 */
export interface LightboxData {
  assets: GalleryAsset[]
  apiBase: string
  versionPublicId: string
  galleryTitle: string
}

const STAGE_WIDTH = 1600

export function initLightbox({ assets, apiBase, versionPublicId, galleryTitle }: LightboxData): void {
  const root = document.getElementById('lightbox')
  if (!root || assets.length === 0) return

  const img = root.querySelector<HTMLImageElement>('[data-lb-img]')
  const pos = root.querySelector<HTMLElement>('[data-lb-pos]')
  const favBtn = root.querySelector<HTMLButtonElement>('[data-lb-favorite]')
  const favLabel = root.querySelector<HTMLElement>('[data-lb-favorite-label]')
  const thumbs = Array.from(root.querySelectorAll<HTMLAnchorElement>('[data-lb-thumb]'))
  if (!img || !pos || !favBtn || !favLabel) return

  let index = 0
  let favorited = new Set<string>()

  const render = (): void => {
    const asset = assets[index]
    img.src = imageUrl(apiBase, versionPublicId, asset.key, STAGE_WIDTH, 'jpeg')
    img.srcset = srcSet(apiBase, versionPublicId, asset.key, 'jpeg')
    pos.textContent = `${galleryTitle} · ${index + 1} von ${assets.length}`
    thumbs.forEach((a, i) => a.classList.toggle('on', i === index))
    renderFavorite()
  }

  const renderFavorite = (): void => {
    const on = favorited.has(assets[index].key)
    favBtn.classList.toggle('on', on)
    favLabel.textContent = on ? 'Favorit entfernen' : 'Favorit'
  }

  const open = (i: number): void => {
    index = i
    render()
    root.classList.add('open')
    document.body.style.overflow = 'hidden'
  }

  const close = (): void => {
    root.classList.remove('open')
    document.body.style.overflow = ''
  }

  const step = (delta: number): void => open((index + delta + assets.length) % assets.length)

  document.querySelectorAll<HTMLElement>('.tile[data-lb-open]').forEach((tile) => {
    tile.addEventListener('click', (event) => {
      event.preventDefault()
      const i = Number(tile.dataset.index)
      if (Number.isInteger(i)) open(i)
    })
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

  favBtn.addEventListener('click', () => {
    void toggleFavorite(apiBase, versionPublicId, assets[index].key).then((on) => {
      const key = assets[index].key
      if (on) favorited.add(key)
      else favorited.delete(key)
      renderFavorite()
    })
  })

  document.addEventListener('keydown', (event) => {
    if (!root.classList.contains('open')) return
    if (event.key === 'Escape') close()
    if (event.key === 'ArrowLeft') step(-1)
    if (event.key === 'ArrowRight') step(1)
  })

  void fetchFavorites(apiBase, versionPublicId).then((keys) => {
    favorited = keys
    if (root.classList.contains('open')) renderFavorite()
  })
}
