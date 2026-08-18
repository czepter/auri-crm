import { describe, expect, it, vi } from 'vitest'
import { buyPass, passCookieName, PASS_MAX_AGE_SECONDS } from './viewPass'

describe('passCookieName', () => {
  it('keeps one gallery\'s pass out of another\'s', () => {
    expect(passCookieName('abc')).not.toBe(passCookieName('def'))
  })
})

describe('buyPass', () => {
  it('sends the password and returns the signed pass', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ pass: 'abc.1.999.sig' }), { status: 200 }),
    )
    vi.stubGlobal('fetch', fetchMock)

    const pass = await buyPass('https://api.example', 'abc', 'req-1', 'basthorst2026')

    expect(pass).toBe('abc.1.999.sig')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('https://api.example/api/galleries/abc/passwort')
    expect(init.method).toBe('POST')
    expect(JSON.parse(init.body)).toEqual({ password: 'basthorst2026' })
  })

  // A gallery with no gate hands one out for the asking, which is what lets
  // the page tell "locked" apart from "does not exist" without the api ever
  // saying which.
  it('asks without a password when there is none to give', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ pass: 'abc.1.999.sig' }), { status: 200 }),
    )
    vi.stubGlobal('fetch', fetchMock)

    await buyPass('https://api.example', 'abc', 'req-1')

    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({})
  })

  it('returns null on a wrong password rather than throwing', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{}', { status: 422 })))

    expect(await buyPass('https://api.example', 'abc', 'req-1', 'falsch')).toBeNull()
  })

  // A cookie that outlives its pass is a gate screen that will not open.
  it('expires the cookie no later than the pass itself', () => {
    expect(PASS_MAX_AGE_SECONDS).toBe(12 * 60 * 60)
  })
})
