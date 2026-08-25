import { describe, expect, it, vi } from 'vitest'
import { isPrerelease, signUpForPrerelease } from './prerelease'

describe('isPrerelease', () => {
  // R40: the one thing that must never trip is a normal published gallery
  // being mistaken for pre-release — that would blank a real gallery's
  // pictures behind the teaser page.
  it('is false for a gallery with no prerelease field at all, like a published or draft response', () => {
    expect(isPrerelease({ draft: false, version: { assets: [] } } as never)).toBe(false)
  })

  it('is false for null — a locked or missing gallery', () => {
    expect(isPrerelease(null)).toBe(false)
  })

  it('is true only when the field is exactly true', () => {
    expect(isPrerelease({ prerelease: true })).toBe(true)
    expect(isPrerelease({ prerelease: false })).toBe(false)
  })
})

describe('signUpForPrerelease', () => {
  it('posts the address to the gallery\'s own signup route', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ status: 'received' }), { status: 202 }))
    vi.stubGlobal('fetch', fetchMock)

    const ok = await signUpForPrerelease('https://api.example', 'abc', 'req-1', 'anna@example.test')

    expect(ok).toBe(true)
    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('https://api.example/api/galleries/abc/benachrichtigung')
    expect(init.method).toBe('POST')
    expect(JSON.parse(init.body)).toEqual({ email: 'anna@example.test' })
  })

  it('reports a malformed address rather than throwing', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{}', { status: 422 })))

    expect(await signUpForPrerelease('https://api.example', 'abc', 'req-1', 'not-an-email')).toBe(false)
  })
})
