import { describe, expect, it } from 'vitest'
import { assetsOf, pickSet } from './sets'

const sets = [{ id: 's1', name: 'Trauung' }]
const assets = [
  { key: 'a', set: 'Trauung', width: 1, height: 1 },
  { key: 'b', set: 'Feier', width: 1, height: 1 },
]

describe('sets', () => {
  it('finds a guest set by id and flags an unknown one', () => {
    expect(pickSet(sets, 's1')).toEqual({ set: sets[0], missing: false })
    expect(pickSet(sets, 'x')).toEqual({ set: null, missing: true })
    expect(pickSet(sets, null)).toEqual({ set: null, missing: false })
  })

  it('narrows the wall to the set and leaves it whole without one', () => {
    expect(assetsOf(assets, sets[0]).map((a) => a.key)).toEqual(['a'])
    expect(assetsOf(assets, null)).toHaveLength(2)
  })
})
