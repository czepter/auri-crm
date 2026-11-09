import { describe, expect, it } from 'vitest'
import { tileStyle } from './tile'

describe('tileStyle', () => {
  it('derives ratio, basis and a last-row cap from the asset size', () => {
    expect(tileStyle({ width: 2000, height: 1000 })).toEqual({
      aspectRatio: '2',
      flex: '200 1 calc(2 * min(var(--gallery-row, 180px), 34vw))',
      maxWidth: 'calc(3 * min(var(--gallery-row, 180px), 34vw))',
    })
  })

  it('falls back to 3:2 without dimensions', () => {
    expect(tileStyle({ width: null, height: null }).aspectRatio).toBe('1.5')
  })
})
