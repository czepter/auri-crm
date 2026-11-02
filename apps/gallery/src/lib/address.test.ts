import { describe, expect, it, vi } from 'vitest'
import { galleryRef, legacyRedirect } from './address'

describe('galleryRef', () => {
  it('keeps one slug under two prefixes apart', () => {
    expect(galleryRef('meier', 'afterwedding')).not.toBe(galleryRef('schulz', 'afterwedding'))
  })
})

describe('legacyRedirect', () => {
  it('sends an old link to the current address, query kept', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ path: 'meier/afterwedding' }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    const response = await legacyRedirect('https://api.example', 'afterwedding', 'req-1', new URL('https://bilder.example/g/afterwedding/download?preview=t'), '/download')

    expect(fetchMock.mock.calls[0][0]).toBe('https://api.example/api/gallery-address/afterwedding')
    expect(response.status).toBe(302)
    expect(response.headers.get('Location')).toBe('/meier/afterwedding/download?preview=t')
  })

  it('answers 404 when the api does not know the link', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{}', { status: 404 })))

    const response = await legacyRedirect('https://api.example', 'afterwedding', 'req-1', new URL('https://bilder.example/g/afterwedding'))

    expect(response.status).toBe(404)
  })
})
