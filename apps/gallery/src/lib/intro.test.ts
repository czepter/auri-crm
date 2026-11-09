import { describe, expect, it } from 'vitest'
import { introPending, introRows, markIntroSeen } from './intro'

function memory() {
  const data = new Map<string, string>()

  return {
    getItem: (k: string) => data.get(k) ?? null,
    setItem: (k: string, v: string) => void data.set(k, v),
  }
}

describe('introRows', () => {
  it('names only what a guest may do', () => {
    expect(introRows({ favorites: true, comments: false })).toEqual(['favorite'])
    expect(introRows({ favorites: false, comments: false })).toEqual([])
  })
})

describe('introPending', () => {
  it('shows once per gallery in this browser', () => {
    const store = memory()
    const rows = introRows({ favorites: true, comments: true })

    expect(introPending('a~b', true, rows, store)).toBe(true)
    markIntroSeen('a~b', store)
    expect(introPending('a~b', true, rows, store)).toBe(false)
    expect(introPending('a~c', true, rows, store)).toBe(true)
  })

  it('stays away when off, empty or without storage', () => {
    const rows = introRows({ favorites: true, comments: false })

    expect(introPending('a~b', false, rows, memory())).toBe(false)
    expect(introPending('a~b', true, [], memory())).toBe(false)
    expect(introPending('a~b', true, rows, null)).toBe(false)
  })
})
