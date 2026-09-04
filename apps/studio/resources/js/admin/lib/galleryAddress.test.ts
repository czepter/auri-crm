import { describe, expect, it } from 'vitest'
import { addressPrefix } from './galleryAddress'

describe('addressPrefix', () => {
  it('keeps everything up to and including the final slash', () => {
    expect(addressPrefix('http://localhost:4321/g/unser-tag')).toBe('http://localhost:4321/g/')
  })

  it('works before a slug is chosen, over the public_id fallback', () => {
    expect(addressPrefix('http://localhost:4321/g/ckv9x1q3z0000qzrmn831i7n')).toBe('http://localhost:4321/g/')
  })
})
