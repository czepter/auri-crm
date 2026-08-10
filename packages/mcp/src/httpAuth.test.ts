import { afterEach, describe, expect, it, vi } from 'vitest'

import { extractBearerToken, verifyBearerToken } from './httpAuth.js'

describe('extractBearerToken', () => {
  it('reads the token out of a well-formed Authorization header', () => {
    expect(extractBearerToken({ headers: { authorization: 'Bearer abc123' } } as never)).toBe('abc123')
  })

  it('is case-insensitive on the scheme', () => {
    expect(extractBearerToken({ headers: { authorization: 'bearer abc123' } } as never)).toBe('abc123')
  })

  it('returns null for a missing header, wrong scheme, or a bearer with no token', () => {
    expect(extractBearerToken({ headers: {} } as never)).toBeNull()
    expect(extractBearerToken({ headers: { authorization: 'Basic abc123' } } as never)).toBeNull()
    expect(extractBearerToken({ headers: { authorization: 'Bearer' } } as never)).toBeNull()
  })
})

describe('verifyBearerToken', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('returns AuthInfo built from a 200 /oauth/token-info response', async () => {
    const fetchMock = vi.fn(async (url: string, init: RequestInit) => {
      expect(url).toBe('http://crm.test/api/oauth/token-info')
      expect((init.headers as Record<string, string>).Authorization).toBe('Bearer good-token')

      return new Response(JSON.stringify({ clientId: 'usr_abc', scopes: ['manage_leads'], expiresAt: 1893456000 }), {
        status: 200,
      })
    })
    vi.stubGlobal('fetch', fetchMock)

    const auth = await verifyBearerToken('good-token', 'http://crm.test/api')

    expect(auth).toEqual({
      token: 'good-token',
      clientId: 'usr_abc',
      scopes: ['manage_leads'],
      expiresAt: 1893456000,
    })
  })

  it('does not lose the base URL\'s own /api path — a plain string join, not URL(\'/x\', base)', async () => {
    const fetchMock = vi.fn(async (url: string) => new Response(JSON.stringify({ clientId: 'x', scopes: [] }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await verifyBearerToken('t', 'http://crm.test/api')

    expect(fetchMock).toHaveBeenCalledWith('http://crm.test/api/oauth/token-info', expect.anything())
  })

  it('returns null when the CRM rejects the token', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response(null, { status: 401 })))

    expect(await verifyBearerToken('bad-token', 'http://crm.test/api')).toBeNull()
  })

  it('returns null rather than throwing when the CRM is unreachable', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => {
        throw new Error('ECONNREFUSED')
      }),
    )

    expect(await verifyBearerToken('t', 'http://crm.test/api')).toBeNull()
  })
})
