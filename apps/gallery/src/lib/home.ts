import { WIDTHS, type Format } from './images'
import type { GalleryDesign } from './design'

export interface HomeTile {
  title: string
  date: string | null
  category: string | null
  path: string
  version: string
  cover: boolean
  protected: boolean
  focal: { x: number; y: number } | null
}

export interface HomeOverview {
  mode: 'overview'
  noindex: boolean
  studio: { name: string; logo: string | null; website: string | null; impressum: string; datenschutz: string | null }
  design: GalleryDesign
  categories: { slug: string; name: string }[]
  category: string | null
  more: boolean
  galleries: HomeTile[]
}

export type HomePayload = { mode: 'none' } | { mode: 'redirect'; url: string } | HomeOverview | { mode: 'overview'; galleries: HomeTile[] }

export interface HomeResult {
  status: number
  headers: Record<string, string>
}

export function homeResponse(payload: HomePayload | null): HomeResult {
  const base = { 'Cache-Control': 'no-store' }

  if (payload === null) {
    return { status: 503, headers: { ...base, 'X-Robots-Tag': 'noindex' } }
  }

  switch (payload.mode) {
    case 'redirect':
      return { status: 302, headers: { ...base, Location: payload.url } }
    case 'overview':
      return 'noindex' in payload && payload.noindex
        ? { status: 200, headers: { ...base, 'X-Robots-Tag': 'noindex, nofollow' } }
        : { status: 200, headers: base }
    default:
      return { status: 404, headers: { ...base, 'X-Robots-Tag': 'noindex' } }
  }
}

export function homeCoverSrcSet(apiBase: string, version: string, format: Format): string {
  return WIDTHS.map((w) => `${apiBase}/api/gallery-home/covers/${version}?w=${w}&fmt=${format} ${w}w`).join(', ')
}

export function homeCoverSrc(apiBase: string, version: string): string {
  return `${apiBase}/api/gallery-home/covers/${version}?w=800&fmt=jpeg`
}

export function tileLoading(index: number, appended = false): { loading: 'eager' | 'lazy'; fetchpriority?: 'high' } {
  if (appended || index >= 3) return { loading: 'lazy' }

  return index === 0 ? { loading: 'eager', fetchpriority: 'high' } : { loading: 'eager' }
}

export function objectPositionOf(focal: HomeTile['focal']): string {
  return focal ? `${focal.x}% ${focal.y}%` : '50% 50%'
}

export function tileDate(tile: HomeTile): string | null {
  return tile.date === null
    ? null
    : new Date(`${tile.date}T00:00:00Z`).toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
}

/** The text of a tile in DOM order, which is also its accessible name. */
export function tileParts(tile: HomeTile): string[] {
  return [tile.category, tile.title, tileDate(tile), tile.protected ? 'Passwortgeschützt' : null].filter((part): part is string => part !== null)
}

export function gateTitle(payload: HomePayload | null): string | null {
  return payload !== null && payload.mode === 'overview' && payload.galleries.length === 1 ? payload.galleries[0].title : null
}

export function moreHref(overview: Pick<HomeOverview, 'more' | 'category'>, page: number): string | null {
  if (!overview.more) return null

  const query = new URLSearchParams()
  if (overview.category) query.set('kategorie', overview.category)
  query.set('seite', String(page + 1))

  return `?${query}`
}

export function filterRow(overview: Pick<HomeOverview, 'categories' | 'category'>): { href: string; label: string; current: boolean }[] | null {
  if (overview.categories.length < 2) return null

  return [
    { href: '/', label: 'Alle', current: overview.category === null },
    ...overview.categories.map((c) => ({ href: `/?kategorie=${encodeURIComponent(c.slug)}`, label: c.name, current: overview.category === c.slug })),
  ]
}
