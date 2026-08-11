import { describe, expect, it } from 'vitest'
import { imageUrl, srcSet, WIDTHS } from './images'

describe('imageUrl', () => {
  it('points at the version-scoped image-proxy route, never a bucket URL', () => {
    const url = imageUrl('https://api.example', 'ver_abc', 'galleries/g1/a.jpg', 800, 'webp')

    expect(url).toBe('https://api.example/api/gallery-versions/ver_abc/images/galleries/g1/a.jpg?w=800&fmt=webp')
  })

  it('encodes each path segment of the key, keeping the slashes as structure', () => {
    const url = imageUrl('https://api.example', 'ver_abc', 'galleries/g 1/a b.jpg', 400, 'avif')

    expect(url).toBe('https://api.example/api/gallery-versions/ver_abc/images/galleries/g%201/a%20b.jpg?w=400&fmt=avif')
  })
})

describe('srcSet', () => {
  it('lists one URL per allowed width, in order, each tagged with its width descriptor', () => {
    const set = srcSet('https://api.example', 'ver_abc', 'galleries/g1/a.jpg', 'avif')
    const entries = set.split(', ')

    expect(entries).toHaveLength(WIDTHS.length)
    entries.forEach((entry, i) => {
      expect(entry).toBe(`https://api.example/api/gallery-versions/ver_abc/images/galleries/g1/a.jpg?w=${WIDTHS[i]}&fmt=avif ${WIDTHS[i]}w`)
    })
  })
})
