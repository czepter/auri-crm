import { describe, expect, it, vi } from 'vitest'
import { formatBytes, readDownloadLink, requestDownloadLink } from './download'

const payload = { email: 'anna@example.test', size: 'original', pin: null }

function answer(status: number, body: unknown = {}) {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status })))
}

describe('requestDownloadLink', () => {
  it('posts the address and size with the view pass as bearer', async () => {
    answer(202, { sent: true })

    expect(await requestDownloadLink('https://api.example', 'abc', 'req-1', payload, 'v')).toBe('sent')

    const [url, init] = vi.mocked(fetch).mock.calls[0]
    expect(url).toBe('https://api.example/api/galleries/abc/download/link')
    expect(new Headers(init?.headers).get('Authorization')).toBe('Bearer v')
    expect(JSON.parse(String(init?.body))).toEqual(payload)
  })

  it('tells a wrong pin, a throttle and any other refusal apart', async () => {
    answer(422, { errors: { pin: ['Dieser Code stimmt nicht.'] } })
    expect(await requestDownloadLink('https://api.example', 'abc', 'req-1', payload, null)).toBe('pin')

    answer(429, { sent: false })
    expect(await requestDownloadLink('https://api.example', 'abc', 'req-1', payload, null)).toBe('throttled')

    answer(422, { errors: { size: ['x'] } })
    expect(await requestDownloadLink('https://api.example', 'abc', 'req-1', payload, null)).toBe('failed')
  })
})

describe('readDownloadLink', () => {
  it('is null once the token is gone', async () => {
    answer(404)

    expect(await readDownloadLink('https://api.example', 'abc', 'req-1', 't')).toBeNull()
  })
})

describe('formatBytes', () => {
  it('switches to GB with a decimal comma', () => {
    expect(formatBytes(1.5 * 1024 ** 3)).toBe('1,5 GB')
    expect(formatBytes(300 * 1024 ** 2)).toBe('300 MB')
  })
})
