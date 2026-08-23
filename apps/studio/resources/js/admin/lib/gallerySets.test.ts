import { describe, expect, it } from 'vitest'
import { initialActiveSetId, nextActiveSetId } from './gallerySets'

const one = [{ id: 'a' }]
const two = [{ id: 'a' }, { id: 'b' }]

describe('initialActiveSetId', () => {
  // R22: a freshly created gallery has exactly one set (Gallery::booted()),
  // and GalleryRail hides Alle along with the rest of the row list while
  // there is only one — so Alle must never be the starting state here.
  it('starts on the sole set when there is only one', () => {
    expect(initialActiveSetId(one)).toBe('a')
  })

  it('starts on Alle once there is a real choice', () => {
    expect(initialActiveSetId(two)).toBeNull()
  })
})

describe('nextActiveSetId', () => {
  it('collapses onto the survivor once a set is deleted down to one, even from Alle', () => {
    expect(nextActiveSetId(one, null)).toBe('a')
  })

  it('collapses onto the survivor even if the deleted set was the active one', () => {
    expect(nextActiveSetId(one, 'b')).toBe('a')
  })

  it('falls back to Alle when the active set vanishes and more than one remain', () => {
    expect(nextActiveSetId(two, 'gone')).toBeNull()
  })

  it('leaves Alle alone when a second set appears', () => {
    expect(nextActiveSetId(two, null)).toBeNull()
  })

  it('leaves an untouched active set alone', () => {
    expect(nextActiveSetId(two, 'a')).toBe('a')
  })
})
