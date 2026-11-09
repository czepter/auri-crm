import { describe, expect, it, vi } from 'vitest'
import { filterGalleries, loadView, saveView } from './galleryFilter'

const now = new Date('2026-11-09T12:00:00Z')

const rows = [
  { id: 'a', status: 'draft', expiresAt: null, projectType: 'wedding' },
  { id: 'b', status: 'published', expiresAt: '2026-11-30', projectType: 'portrait' },
  { id: 'c', status: 'published', expiresAt: '2027-06-01' },
  { id: 'd', status: 'withdrawn', expiresAt: '2026-11-20' },
  { id: 'e', status: 'published', expiresAt: '2026-11-01' },
]

describe('filterGalleries', () => {
  it('keeps every row for all', () => {
    expect(filterGalleries(rows, 'all', now)).toHaveLength(5)
  })

  it('filters by status', () => {
    expect(filterGalleries(rows, 'draft', now).map((row) => row.id)).toEqual(['a'])
  })

  it('counts only published galleries that end within the window and not yet past', () => {
    expect(filterGalleries(rows, 'expiring', now).map((row) => row.id)).toEqual(['b'])
  })
})

describe('project type', () => {
  it('narrows by type and combines with the status filter', () => {
    expect(filterGalleries(rows, 'all', now, 'wedding').map((row) => row.id)).toEqual(['a'])
    expect(filterGalleries(rows, 'published', now, 'portrait').map((row) => row.id)).toEqual(['b'])
    expect(filterGalleries(rows, 'draft', now, 'portrait')).toEqual([])
  })
})

describe('view memory', () => {
  it('returns the stored choice and survives blocked storage', () => {
    const store = new Map<string, string>()
    vi.stubGlobal('localStorage', { getItem: (key: string) => store.get(key) ?? null, setItem: (key: string, value: string) => store.set(key, value) })
    saveView('grid')
    expect(loadView()).toBe('grid')

    vi.stubGlobal('localStorage', { getItem: () => { throw new Error('blocked') }, setItem: () => { throw new Error('blocked') } })
    expect(() => saveView('list')).not.toThrow()
    expect(loadView()).toBe('list')
    vi.unstubAllGlobals()
  })
})
