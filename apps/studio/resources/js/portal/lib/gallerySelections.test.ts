import { describe, expect, it } from 'vitest'
import { ALL, countOf, selections, visible } from './gallerySelections'

const LABELS = { all: 'Alle', heart: 'Favoriten' }

describe('selections', () => {
  it('names the client their own lists, the heart first and under its own label', () => {
    const result = selections(
      [
        { listId: 'l1', name: null, keys: ['a', 'b'] },
        { listId: 'l2', name: 'Album', keys: ['a'] },
      ],
      null,
      LABELS,
    )

    expect(result.map((s) => s.label)).toEqual(['Alle', 'Favoriten', 'Album'])
    expect(result[0].keys).toBeNull()
  })

  it('gives a guest their hearts and nothing named', () => {
    expect(selections(null, ['b'], LABELS)).toEqual([
      { id: ALL, label: 'Alle', keys: null },
      { id: 'favoriten', label: 'Favoriten', keys: ['b'] },
    ])
  })

  it('offers only Alle where no selection can exist at all', () => {
    expect(selections(null, null, LABELS)).toHaveLength(1)
  })
})

describe('countOf', () => {
  it('counts the whole gallery for Alle and the selection for everything else', () => {
    expect(countOf({ id: ALL, label: 'Alle', keys: null }, 33)).toBe(33)
    expect(countOf({ id: 'l1', label: 'Album', keys: ['a', 'b'] }, 33)).toBe(2)
  })
})

describe('visible', () => {
  const assets = [{ key: 'a' }, { key: 'b' }, { key: 'c' }]

  it('keeps the version order rather than the selection order', () => {
    expect(visible(assets, ['c', 'a'])).toEqual([{ key: 'a' }, { key: 'c' }])
  })

  it('shows everything when nothing is selected, and nothing for an empty selection', () => {
    expect(visible(assets, null)).toHaveLength(3)
    expect(visible(assets, [])).toHaveLength(0)
  })
})
