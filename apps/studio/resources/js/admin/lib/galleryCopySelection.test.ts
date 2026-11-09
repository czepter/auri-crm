import { describe, expect, it } from 'vitest'
import { copyCount, copyIds } from './galleryCopySelection'

const all = ['a', 'b', 'c']

describe('copyIds', () => {
  it('sends no list for none or all', () => {
    expect(copyIds(new Set(), all)).toBeNull()
    expect(copyIds(new Set(['c', 'a', 'b']), all)).toBeNull()
  })

  it('sends the chosen ids in gallery order and drops strangers', () => {
    expect(copyIds(new Set(['c', 'a', 'x']), all)).toEqual(['a', 'c'])
  })
})

describe('copyCount', () => {
  it('counts the whole gallery when nothing is chosen', () => {
    expect(copyCount(new Set(), all)).toBe(3)
    expect(copyCount(new Set(['b']), all)).toBe(1)
  })
})
