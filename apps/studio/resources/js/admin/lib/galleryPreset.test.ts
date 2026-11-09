import { describe, expect, it } from 'vitest'
import { DEFAULT_RELEASE, addSet, releaseOf, withAccess, withoutSet } from './galleryPreset'

const open = { viewMode: null, downloadRule: null, downloadSizes: null, permissions: null, introEnabled: null }

describe('releaseOf', () => {
  it('is null while the preset leaves the release open', () => {
    expect(releaseOf(open)).toBeNull()
  })

  it('fills what the preset leaves out from the defaults', () => {
    expect(releaseOf({ ...open, downloadRule: 'after_payment', introEnabled: true })).toEqual({
      ...DEFAULT_RELEASE,
      download_rule: 'after_payment',
      intro_enabled: true,
    })
  })
})

describe('sets', () => {
  const sets = [{ name: 'Highlights', access: 'internal' as const }]

  it('adds a trimmed name as internal', () => {
    expect(addSet(sets, '  Fotobox ')).toEqual([...sets, { name: 'Fotobox', access: 'internal' }])
  })

  it('ignores an empty or repeated name', () => {
    expect(addSet(sets, '  ')).toBe(sets)
    expect(addSet(sets, 'highlights')).toBe(sets)
  })

  it('changes the access of one set and removes another', () => {
    const two = addSet(sets, 'Fotobox')

    expect(withAccess(two, 1, 'guests')[1].access).toBe('guests')
    expect(withAccess(two, 1, 'guests')[0].access).toBe('internal')
    expect(withoutSet(two, 0)).toEqual([{ name: 'Fotobox', access: 'internal' }])
  })
})
