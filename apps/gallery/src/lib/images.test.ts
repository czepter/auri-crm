import { describe, expect, it } from 'vitest'
import { draftImageBase, FORMATS, imageUrl, passGate, previewGate, srcSet, versionImageBase, WIDTHS } from './images'

describe('imageUrl', () => {
  it('points at the version-scoped image-proxy route, never a bucket URL', () => {
    const url = imageUrl('https://api.example', versionImageBase('ver_abc'), 'galleries/g1/a.jpg', 800, 'webp')

    expect(url).toBe('https://api.example/api/gallery-versions/ver_abc/images/galleries/g1/a.jpg?w=800&fmt=webp')
  })

  it('encodes each path segment of the key, keeping the slashes as structure', () => {
    const url = imageUrl('https://api.example', versionImageBase('ver_abc'), 'galleries/g 1/a b.jpg', 400, 'avif')

    expect(url).toBe('https://api.example/api/gallery-versions/ver_abc/images/galleries/g%201/a%20b.jpg?w=400&fmt=avif')
  })

  it('carries a pass as a query gate', () => {
    const url = imageUrl('https://api.example', versionImageBase('ver_abc'), 'a.jpg', 400, 'avif', passGate('p4ss'))

    expect(url).toBe('https://api.example/api/gallery-versions/ver_abc/images/a.jpg?w=400&fmt=avif&pass=p4ss')
  })

  // R31: a draft has no GalleryVersion, so it addresses the gallery's own
  // draft route with a preview pass instead of a version pass — the whole
  // reason GalleryDraftImageController is its own route.
  it('addresses a draft asset under the gallery, gated by a preview pass', () => {
    const url = imageUrl('https://api.example', draftImageBase('gal_abc'), 'a.jpg', 400, 'avif', previewGate('prev-token'))

    expect(url).toBe('https://api.example/api/galleries/gal_abc/entwurf-bilder/a.jpg?w=400&fmt=avif&preview=prev-token')
  })
})

describe('srcSet', () => {
  it('lists one URL per allowed width, in order, each tagged with its width descriptor', () => {
    const set = srcSet('https://api.example', versionImageBase('ver_abc'), 'galleries/g1/a.jpg', 'avif')
    const entries = set.split(', ')

    expect(entries).toHaveLength(WIDTHS.length)
    entries.forEach((entry, i) => {
      expect(entry).toBe(`https://api.example/api/gallery-versions/ver_abc/images/galleries/g1/a.jpg?w=${WIDTHS[i]}&fmt=avif ${WIDTHS[i]}w`)
    })
  })
})

describe('the allow-list', () => {
  // Written out, not imported: a test that reads its expectation from the
  // thing it checks asserts nothing. These numbers standing twice is the
  // assertion. App\Gallery\Support\ImageVariant::WIDTHS is the source.
  it('carries the widths and formats GalleryImageController accepts', () => {
    expect([...WIDTHS]).toEqual([400, 800, 1200, 1600, 2400])
    expect([...FORMATS]).toEqual(['avif', 'webp', 'jpeg'])
  })
})
