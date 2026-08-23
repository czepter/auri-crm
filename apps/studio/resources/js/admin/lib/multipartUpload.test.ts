import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { checkDuplicates, fingerprintOf, partCount, partRange, startOrResumeUpload } from './multipartUpload'

describe('fingerprintOf', () => {
  it('reads name, size and lastModified straight off the File — never a storage path, since the browser is never given one', () => {
    const file = new File(['hello world'], 'IMG_4788.jpg', { lastModified: 1_749_000_000_000 })

    expect(fingerprintOf(file)).toEqual({
      filename: 'IMG_4788.jpg',
      file_size: 11,
      file_modified_at: 1_749_000_000_000,
    })
  })
})

describe('partCount', () => {
  it('chunks evenly', () => {
    expect(partCount(24 * 1024 * 1024, 8 * 1024 * 1024)).toBe(3)
  })

  it('rounds a remainder up into one more, smaller part', () => {
    expect(partCount(20 * 1024 * 1024, 8 * 1024 * 1024)).toBe(3)
  })

  it('is never zero, even for an empty file', () => {
    expect(partCount(0, 8 * 1024 * 1024)).toBe(1)
  })
})

describe('partRange', () => {
  const partSize = 8 * 1024 * 1024

  it('gives the first part the first partSize bytes', () => {
    expect(partRange(1, partSize, 20 * 1024 * 1024)).toEqual([0, partSize])
  })

  it('caps the last part at the file size rather than overrunning it', () => {
    expect(partRange(3, partSize, 20 * 1024 * 1024)).toEqual([16 * 1024 * 1024, 20 * 1024 * 1024])
  })
})

describe('startOrResumeUpload', () => {
  beforeEach(() => vi.stubGlobal('document', { cookie: '' }))
  afterEach(() => vi.unstubAllGlobals())

  // A resumed session keeps the set it was started against — the response
  // can name a different set than the one this call asked for, and the
  // caller (Images.vue) uses that to warn when an upload lands elsewhere.
  it('surfaces the set the session actually landed in, not just the one requested', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ upload: { public_id: 'u1', part_size: 8, gallery_set_id: 'other-set' }, parts: [] }),
    })
    vi.stubGlobal('fetch', fetchMock)

    const file = new File(['x'], 'a.jpg')
    const session = await startOrResumeUpload('/uploads', file, 'requested-set')

    expect(session.gallerySetId).toBe('other-set')
  })

  it('sends replaces_asset_id only when one is given', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ upload: { public_id: 'u1', part_size: 8, gallery_set_id: 's1' }, parts: [] }),
    })
    vi.stubGlobal('fetch', fetchMock)

    const file = new File(['x'], 'a.jpg')
    await startOrResumeUpload('/uploads', file, 's1', 'asset-42')

    const body = JSON.parse(fetchMock.mock.calls[0][1].body)
    expect(body.replaces_asset_id).toBe('asset-42')

    await startOrResumeUpload('/uploads', file, 's1')
    const bodyWithout = JSON.parse(fetchMock.mock.calls[1][1].body)
    expect(bodyWithout.replaces_asset_id).toBeUndefined()
  })
})

describe('checkDuplicates', () => {
  beforeEach(() => vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=tok3n' }))
  afterEach(() => vi.unstubAllGlobals())

  // Critical 1: this precheck's own fetch was the one raw call in the app
  // that forgot the header — PreventRequestForgery answered with a 419 HTML
  // body, `.json()` threw inside an unawaited promise, and no upload ever
  // started. Pins the header rather than trusting the import stays in place.
  it('carries the XSRF header a plain fetch needs to survive PreventRequestForgery', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ duplicates: [] }) })
    vi.stubGlobal('fetch', fetchMock)

    await checkDuplicates('g1', [{ filename: 'a.jpg', size: 10 }])

    expect(fetchMock.mock.calls[0][1].headers['X-XSRF-TOKEN']).toBe('tok3n')
  })

  it('throws on a non-2xx response instead of handing back a body with no duplicates key', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 419, statusText: 'unknown status' })
    vi.stubGlobal('fetch', fetchMock)

    await expect(checkDuplicates('g1', [{ filename: 'a.jpg', size: 10 }])).rejects.toThrow('419')
  })
})
