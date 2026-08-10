import { describe, expect, it } from 'vitest'
import { scrubEvent } from './scrub'

// G-13's acceptance criterion: "an event captured from a request carrying a
// guest token, a service key and a signed URL contains none of the three,
// proven by a test on the scrub hook rather than by inspection." This is
// that test — one event, all three, none surviving.
describe('scrubEvent', () => {
  it('strips a guest token, a service key and a signed download URL from the same event', () => {
    const event = {
      request: {
        url: 'https://gallery.example/g/gal_abc?expires=1999999999&signature=abcdef0123456789',
        headers: {
          Authorization: 'Bearer guest-token-secret',
          'X-Service-Key': 'ssr-service-key-secret',
          Accept: 'application/json',
        },
      },
    }

    const scrubbed = scrubEvent(event)
    const serialized = JSON.stringify(scrubbed)

    expect(serialized).not.toContain('guest-token-secret')
    expect(serialized).not.toContain('ssr-service-key-secret')
    expect(serialized).not.toContain('abcdef0123456789')

    expect(scrubbed.request?.headers?.Authorization).toBe('[Filtered]')
    expect(scrubbed.request?.headers?.['X-Service-Key']).toBe('[Filtered]')
    expect(scrubbed.request?.headers?.Accept).toBe('application/json')
    expect(scrubbed.request?.url).not.toContain('signature=abcdef0123456789')
  })

  it('redacts the Cookie header entirely', () => {
    const event = {
      request: { headers: { Cookie: 'gallery_session=abc123' } },
    }

    expect(scrubEvent(event).request?.headers?.Cookie).toBe('[Filtered]')
  })

  it('drops cookies rather than partially redacting them', () => {
    const event = {
      request: { cookies: { gallery_session: 'abc123' } },
    }

    expect(scrubEvent(event).request?.cookies).toBeUndefined()
  })

  it('strips the signature and expiry from a query string carried separately from the url', () => {
    const event = {
      request: { query_string: 'w=800&fmt=avif&expires=1999999999&signature=abcdef0123456789' },
    }

    const scrubbed = scrubEvent(event)

    expect(scrubbed.request?.query_string).not.toContain('abcdef0123456789')
    expect(scrubbed.request?.query_string).toContain('w=800')
  })

  it('redacts a gallery password or PIN submitted in the request body, recursively', () => {
    const event = {
      request: {
        data: {
          password: 'hunter2',
          gallery_pin: '4821',
          nested: { pin: '1234' },
          asset_key: 'a.jpg',
        },
      },
    }

    const data = scrubEvent(event).request?.data as Record<string, unknown>

    expect(data.password).toBe('[Filtered]')
    expect(data.gallery_pin).toBe('[Filtered]')
    expect((data.nested as Record<string, unknown>).pin).toBe('[Filtered]')
    expect(data.asset_key).toBe('a.jpg')
  })

  it('redacts sensitive fields carried in extra context too', () => {
    const event = { extra: { guest_token: 'secret-guest-token', gallery_id: 'gal_abc' } }

    const extra = scrubEvent(event).extra as Record<string, unknown>

    expect(extra.guest_token).toBe('[Filtered]')
    expect(extra.gallery_id).toBe('gal_abc')
  })

  it('leaves an event with no request or extra data untouched', () => {
    const event = { request: undefined, message: 'boom' }

    expect(scrubEvent(event)).toEqual({ request: undefined, message: 'boom' })
  })
})
