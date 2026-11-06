import { describe, expect, it } from 'vitest'
import { tileStyle } from './tile'

describe('tileStyle', () => {
  it('derives ratio, basis and a last-row cap from the asset size', () => {
    const style = tileStyle({ width: 2000, height: 1000 })

    expect(style).toContain('aspect-ratio:2;')
    expect(style).toContain('flex:200 1 calc(2 * min(180px, 34vw))')
    expect(style).toContain('max-width:calc(3 * min(180px, 34vw))')
  })

  it('falls back to 3:2 without dimensions', () => {
    expect(tileStyle({ width: null, height: null })).toContain('aspect-ratio:1.5;')
  })
})
