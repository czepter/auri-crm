import { describe, expect, it } from 'vitest'
import { addressPrefix } from './galleryAddress'

describe('addressPrefix', () => {
  it('keeps everything up to and including the final slash', () => {
    expect(addressPrefix('http://localhost:4321/meier/unser-tag')).toBe('http://localhost:4321/meier/')
  })
})
