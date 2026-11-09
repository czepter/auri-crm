import { describe, expect, it } from 'vitest'
import { titleMatches } from './galleryDelete'

describe('titleMatches', () => {
  it('needs the exact title', () => {
    expect(titleMatches('Hochzeit', 'Hochzeit')).toBe(true)
    expect(titleMatches('hochzeit', 'Hochzeit')).toBe(false)
    expect(titleMatches('Hochzeit ', 'Hochzeit')).toBe(false)
    expect(titleMatches('', 'Hochzeit')).toBe(false)
  })

  it('lets an untitled gallery through', () => {
    expect(titleMatches('', null)).toBe(true)
    expect(titleMatches('', '')).toBe(true)
  })
})
