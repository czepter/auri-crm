import { describe, expect, it } from 'vitest'
import { FORMATS, GRID_WIDTHS, srcSet, STAGE_WIDTHS, WIDTHS } from './images'

describe('the allow-list', () => {
  // Written out, not imported from ImageVariant: a test that reads its
  // expectation from the thing it checks asserts nothing. These numbers
  // standing twice is the assertion.
  it('carries the widths and formats GalleryImageController accepts', () => {
    expect([...WIDTHS]).toEqual([400, 800, 1200, 1600, 2400])
    expect([...FORMATS]).toEqual(['avif', 'webp', 'jpeg'])
  })

  it('offers only widths from that list on either screen', () => {
    for (const width of [...GRID_WIDTHS, ...STAGE_WIDTHS]) {
      expect(WIDTHS).toContain(width)
    }
  })
})

describe('srcSet', () => {
  it('appends the query to the path the server built, one entry per width', () => {
    const set = srcSet('/galerie-bilder/ver_abc/a.jpg', 'webp', GRID_WIDTHS)

    expect(set.split(', ')).toEqual([
      '/galerie-bilder/ver_abc/a.jpg?w=400&fmt=webp 400w',
      '/galerie-bilder/ver_abc/a.jpg?w=800&fmt=webp 800w',
      '/galerie-bilder/ver_abc/a.jpg?w=1200&fmt=webp 1200w',
      '/galerie-bilder/ver_abc/a.jpg?w=1600&fmt=webp 1600w',
    ])
  })
})
