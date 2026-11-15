import { describe, expect, it } from 'vitest'
import { WIDTHS } from './images'
import { filterRow, gateTitle, homeCoverSrcSet, homeResponse, moreHref, tileLoading, tileParts, objectPositionOf, type HomeOverview, type HomeTile } from './home'

const tile: HomeTile = { title: 'Anna & Ben', date: '2026-09-14', category: 'Hochzeit', path: 'meier/anna', version: 'v1', cover: true, protected: true, focal: { x: 20, y: 70 } }

const overview = (patch: Partial<HomeOverview> = {}): HomeOverview => ({
  mode: 'overview', noindex: true, studio: { name: 'S', logo: null, website: null, impressum: '/i', datenschutz: null },
  design: {} as HomeOverview['design'], categories: [], category: null, more: false, galleries: [tile], ...patch,
})

describe('homeResponse', () => {
  it('maps every mode to status and headers', () => {
    expect(homeResponse({ mode: 'none' })).toEqual({ status: 404, headers: { 'Cache-Control': 'no-store', 'X-Robots-Tag': 'noindex' } })
    expect(homeResponse({ mode: 'redirect', url: 'https://x.de' })).toEqual({ status: 302, headers: { 'Cache-Control': 'no-store', Location: 'https://x.de' } })
    expect(homeResponse(overview()).headers['X-Robots-Tag']).toBe('noindex, nofollow')
    expect(homeResponse(overview({ noindex: false }))).toEqual({ status: 200, headers: { 'Cache-Control': 'no-store' } })
    expect(homeResponse(null)).toEqual({ status: 503, headers: { 'Cache-Control': 'no-store', 'X-Robots-Tag': 'noindex' } })
  })
})

describe('tiles', () => {
  it('builds cover urls without key or pass for every width', () => {
    const set = homeCoverSrcSet('http://api', 'v1', 'webp')

    expect(set.split(', ')).toHaveLength(WIDTHS.length)
    expect(set).toContain('http://api/api/gallery-home/covers/v1?w=400&fmt=webp 400w')
    expect(set).not.toMatch(/key|pass/)
  })

  it('loads the first three eagerly, the first with high priority, appended ones lazily', () => {
    expect(tileLoading(0)).toEqual({ loading: 'eager', fetchpriority: 'high' })
    expect(tileLoading(2)).toEqual({ loading: 'eager' })
    expect(tileLoading(3)).toEqual({ loading: 'lazy' })
    expect(tileLoading(0, true)).toEqual({ loading: 'lazy' })
  })

  it('positions by focal point, centred without', () => {
    expect(objectPositionOf({ x: 20, y: 70 })).toBe('20% 70%')
    expect(objectPositionOf(null)).toBe('50% 50%')
  })

  it('orders category, title, date, lock', () => {
    expect(tileParts(tile)).toEqual(['Hochzeit', 'Anna & Ben', '14. September 2026', 'Passwortgeschützt'])
    expect(tileParts({ ...tile, category: null, date: null, protected: false })).toEqual(['Anna & Ben'])
  })
})

describe('gateTitle', () => {
  it('returns the single tile title, else null', () => {
    expect(gateTitle(overview())).toBe('Anna & Ben')
    expect(gateTitle(overview({ galleries: [] }))).toBeNull()
    expect(gateTitle({ mode: 'redirect', url: 'x' })).toBeNull()
    expect(gateTitle(null)).toBeNull()
  })
})

describe('moreHref', () => {
  it('keeps the category and advances the page', () => {
    expect(moreHref({ more: true, category: 'hochzeit' }, 2)).toBe('?kategorie=hochzeit&seite=3')
    expect(moreHref({ more: true, category: null }, 1)).toBe('?seite=2')
    expect(moreHref({ more: false, category: null }, 1)).toBeNull()
  })
})

describe('filterRow', () => {
  it('is null below two categories and marks the active one', () => {
    expect(filterRow({ categories: [{ slug: 'a', name: 'A' }], category: null })).toBeNull()

    const row = filterRow({ categories: [{ slug: 'a', name: 'A' }, { slug: 'b', name: 'B' }], category: 'b' })!

    expect(row.map((r) => r.current)).toEqual([false, false, true])
    expect(row[1].href).toBe('/?kategorie=a')
  })
})
