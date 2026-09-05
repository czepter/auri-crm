import { describe, expect, it } from 'vitest'
import { moveRow } from './rows'

describe('moveRow', () => {
  it('moves a row down and closes the gap behind it', () => {
    expect(moveRow(['a', 'b', 'c'], 0, 1)).toEqual(['b', 'a', 'c'])
  })

  it('moves a row up', () => {
    expect(moveRow(['a', 'b', 'c'], 2, 0)).toEqual(['c', 'a', 'b'])
  })

  it('returns the same array when the target is out of range', () => {
    const rows = ['a', 'b']

    expect(moveRow(rows, 0, -1)).toBe(rows)
    expect(moveRow(rows, 1, 2)).toBe(rows)
  })

  it('returns the same array when nothing moves', () => {
    const rows = ['a', 'b']

    expect(moveRow(rows, 1, 1)).toBe(rows)
  })
})
