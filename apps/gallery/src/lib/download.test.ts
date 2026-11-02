import { describe, expect, it, vi } from 'vitest'
import { buyDownloadPass, downloadCookieName, formatBytes, readOffer } from './download'

describe('downloadCookieName', () => {
  it("keeps one gallery's pin pass out of another's", () => {
    expect(downloadCookieName('abc')).not.toBe(downloadCookieName('def'))
  })
})

describe('readOffer', () => {
  it('carries the guest and pin passes as headers, never in the URL, and the view pass as bearer', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ step: 'ready' }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    const { offer } = await readOffer('https://api.example', 'abc', 'req-1', { view: 'v', guest: 'g', download: 'd' })

    expect(offer?.step).toBe('ready')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('https://api.example/api/galleries/abc/download')

    const headers = new Headers(init.headers)
    expect(headers.get('Authorization')).toBe('Bearer v')
    expect(headers.get('X-Guest-Pass')).toBe('g')
    expect(headers.get('X-Download-Pass')).toBe('d')
  })

  it('keeps the status when the api refuses', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 404 })))

    expect(await readOffer('https://api.example', 'abc', 'req-1', { view: null, guest: null, download: null })).toEqual({
      status: 404,
      offer: null,
    })
  })
})

describe('buyDownloadPass', () => {
  it('is null for a wrong pin', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{}', { status: 422 })))

    expect(await buyDownloadPass('https://api.example', 'abc', 'req-1', '0000', null)).toBeNull()
  })
})

describe('formatBytes', () => {
  it('switches to GB with a decimal comma', () => {
    expect(formatBytes(1.5 * 1024 ** 3)).toBe('1,5 GB')
    expect(formatBytes(300 * 1024 ** 2)).toBe('300 MB')
  })
})
