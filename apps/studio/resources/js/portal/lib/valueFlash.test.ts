import { describe, expect, it } from 'vitest'
import { flashDirection } from './valueFlash'

describe('flashDirection', () => {
  it('names the direction of a change', () => {
    expect(flashDirection(100, 150)).toBe('up')
    expect(flashDirection(150, 100)).toBe('down')
  })

  it('ignores an unchanged value', () => {
    expect(flashDirection(100, 100)).toBeNull()
  })
})
