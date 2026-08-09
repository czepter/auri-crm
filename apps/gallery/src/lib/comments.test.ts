import { afterEach, describe, expect, it, vi } from 'vitest'
import { fetchComments, postComment } from './comments'
import type { GuestIdStore } from './favorites'

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

describe('fetchComments', () => {
  it('returns the comments the api reports for this asset', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ comments: [{ body: 'Schön!', created_at: '2026-08-10T12:00:00Z' }] }), { status: 200 }),
    )
    vi.stubGlobal('fetch', fetchMock)

    const comments = await fetchComments('https://api.example', 'ver_abc', 'galleries/g 1/a.jpg')

    expect(comments).toEqual([{ body: 'Schön!', createdAt: '2026-08-10T12:00:00Z' }])
    expect(fetchMock).toHaveBeenCalledWith('https://api.example/api/gallery-versions/ver_abc/comments?asset_key=galleries%2Fg%201%2Fa.jpg')
  })

  it('degrades to an empty list on a failed response, rather than throwing', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('', { status: 500 })))

    expect(await fetchComments('https://api.example', 'ver_abc', 'a.jpg')).toEqual([])
  })

  it('degrades to an empty list when the network call itself rejects', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')))

    expect(await fetchComments('https://api.example', 'ver_abc', 'a.jpg')).toEqual([])
  })
})

describe('postComment', () => {
  it('posts the guest id, asset key and body, resolving to the stored comment', async () => {
    const store = memoryStore()
    store.setItem('gallery_guest_id', 'guest-1')
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ comment: { body: 'Toll!', created_at: '2026-08-10T12:00:00Z' } }), { status: 201 }),
    )
    vi.stubGlobal('fetch', fetchMock)

    const comment = await postComment('https://api.example', 'ver_abc', 'a.jpg', 'Toll!', store)

    expect(comment).toEqual({ body: 'Toll!', createdAt: '2026-08-10T12:00:00Z' })
    expect(fetchMock).toHaveBeenCalledWith(
      'https://api.example/api/gallery-versions/ver_abc/comments',
      expect.objectContaining({
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ guest_id: 'guest-1', asset_key: 'a.jpg', body: 'Toll!' }),
      }),
    )
  })

  it('resolves to null on a failed response, rather than throwing', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('', { status: 422 })))

    expect(await postComment('https://api.example', 'ver_abc', 'a.jpg', 'x')).toBeNull()
  })
})
