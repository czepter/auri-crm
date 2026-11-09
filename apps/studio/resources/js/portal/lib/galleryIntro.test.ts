import { describe, expect, it } from 'vitest'
import { introPending, introRows, markIntroSeen } from './galleryIntro'

function memory(): Storage {
  const data = new Map<string, string>()

  return {
    getItem: (k: string) => data.get(k) ?? null,
    setItem: (k: string, v: string) => void data.set(k, v),
  } as Storage
}

describe('introRows', () => {
  it('lists only what the visitor may do', () => {
    expect(introRows({ lists: true, comments: false, hide: true })).toEqual(['favorite', 'hide'])
    expect(introRows({ lists: false, comments: false, hide: false })).toEqual([])
  })
})

describe('introPending', () => {
  it('shows once per browser and gallery', () => {
    const store = memory()
    const rows = introRows({ lists: true, comments: true, hide: true })

    expect(introPending('g1', true, rows, store)).toBe(true)
    markIntroSeen('g1', store)
    expect(introPending('g1', true, rows, store)).toBe(false)
    expect(introPending('g2', true, rows, store)).toBe(true)
  })

  it('stays away when switched off, without rows or without storage', () => {
    const rows = introRows({ lists: true, comments: false, hide: false })

    expect(introPending('g1', false, rows, memory())).toBe(false)
    expect(introPending('g1', true, [], memory())).toBe(false)
    expect(introPending('g1', true, rows, null)).toBe(false)
  })
})
