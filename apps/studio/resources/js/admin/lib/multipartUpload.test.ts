import { describe, expect, it } from 'vitest'
import { fingerprintOf, partCount, partRange } from './multipartUpload'

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
