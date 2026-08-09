import { afterEach, describe, expect, it, vi } from 'vitest'
import { fetchFavorites, guestId, toggleFavorite, type GuestIdStore } from './favorites'

/** An in-memory stand-in for localStorage — vitest's `node` environment has no real one. */
function memoryStore(): GuestIdStore {
  const values = new Map<string, string>()

  return {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => void values.set(key, value),
  }
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('guestId', () => {
  it('mints a fresh id and persists it when the store holds none yet', () => {
    const store = memoryStore()

    const id = guestId(store)

    expect(id).toMatch(/^[0-9a-f-]{36}$/)
    expect(store.getItem('gallery_guest_id')).toBe(id)
  })

  it('returns the same id on every later call, never minting a second one', () => {
    const store = memoryStore()

    const first = guestId(store)
    const second = guestId(store)

    expect(second).toBe(first)
  })

  it('reuses whatever the store already held, rather than overwriting it', () => {
    const store = memoryStore()
    store.setItem('gallery_guest_id', 'already-a-guest')

    expect(guestId(store)).toBe('already-a-guest')
  })
})

describe('fetchFavorites', () => {
  it('returns the favorited keys the api reports for this guest', async () => {
    const store = memoryStore()
    store.setItem('gallery_guest_id', 'guest-1')
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ favorited: ['a.jpg', 'b.jpg'] }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    const keys = await fetchFavorites('https://api.example', 'ver_abc', store)

    expect(keys).toEqual(new Set(['a.jpg', 'b.jpg']))
    expect(fetchMock).toHaveBeenCalledWith('https://api.example/api/gallery-versions/ver_abc/favorites?guest_id=guest-1')
  })

  it('degrades to an empty set on a failed response, rather than throwing', async () => {
    const store = memoryStore()
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('', { status: 500 })))

    expect(await fetchFavorites('https://api.example', 'ver_abc', store)).toEqual(new Set())
  })

  it('degrades to an empty set when the network call itself rejects', async () => {
    const store = memoryStore()
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')))

    expect(await fetchFavorites('https://api.example', 'ver_abc', store)).toEqual(new Set())
  })
})

describe('toggleFavorite', () => {
  it('posts the guest id and the encoded key, resolving to the resulting state', async () => {
    const store = memoryStore()
    store.setItem('gallery_guest_id', 'guest-1')
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ favorited: true }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    const favorited = await toggleFavorite('https://api.example', 'ver_abc', 'galleries/g 1/a.jpg', store)

    expect(favorited).toBe(true)
    expect(fetchMock).toHaveBeenCalledWith(
      'https://api.example/api/gallery-versions/ver_abc/favorites/galleries/g%201/a.jpg',
      expect.objectContaining({
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ guest_id: 'guest-1' }),
      }),
    )
  })
})
