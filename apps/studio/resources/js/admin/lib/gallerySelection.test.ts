import { describe, expect, it } from 'vitest'
import { applyClick, range, toggle } from './gallerySelection'

const IDS = ['a', 'b', 'c', 'd', 'e']

describe('toggle', () => {
  it('adds an id that is missing and removes one that is present', () => {
    expect([...toggle(new Set(['a']), 'b')]).toEqual(['a', 'b'])
    expect([...toggle(new Set(['a', 'b']), 'b')]).toEqual(['a'])
  })
})

describe('range', () => {
  it('spans a range in either direction', () => {
    expect(range(IDS, 'b', 'd')).toEqual(['b', 'c', 'd'])
    expect(range(IDS, 'd', 'b')).toEqual(['b', 'c', 'd'])
  })

  it('returns just the anchor when it equals the target', () => {
    expect(range(IDS, 'c', 'c')).toEqual(['c'])
  })
})

describe('applyClick', () => {
  it('replaces the selection on a plain click', () => {
    const next = applyClick({ selected: new Set(['a', 'b']), anchor: 'a' }, IDS, 'd', {})

    expect([...next.selected]).toEqual(['d'])
    expect(next.anchor).toBe('d')
  })

  it('extends from the anchor on shift, keeping the anchor', () => {
    const next = applyClick({ selected: new Set(['b']), anchor: 'b' }, IDS, 'd', { shift: true })

    expect([...next.selected]).toEqual(['b', 'c', 'd'])
    expect(next.anchor).toBe('b')
  })

  it('extends backward when the target precedes the anchor', () => {
    const next = applyClick({ selected: new Set(['d']), anchor: 'd' }, IDS, 'b', { shift: true })

    expect([...next.selected]).toEqual(['b', 'c', 'd'])
    expect(next.anchor).toBe('d')
  })

  // Der Punkt aus dem Auftrag: waehrend des Shift-Ziehens darf der Anker
  // nicht wandern, sonst laesst sich die Strecke nie verkuerzen.
  it('re-measures from the same anchor across successive shift-clicks', () => {
    const first = applyClick({ selected: new Set(['b']), anchor: 'b' }, IDS, 'd', { shift: true })
    const second = applyClick(first, IDS, 'c', { shift: true })

    expect([...second.selected]).toEqual(['b', 'c'])
    expect(second.anchor).toBe('b')
  })

  it('adds without clearing on meta, and moves the anchor to the clicked id', () => {
    const next = applyClick({ selected: new Set(['a']), anchor: 'a' }, IDS, 'd', { meta: true })

    expect([...next.selected]).toEqual(['a', 'd'])
    expect(next.anchor).toBe('d')
  })

  it('removes an already-selected id on meta instead of adding it again', () => {
    const next = applyClick({ selected: new Set(['a', 'd']), anchor: 'a' }, IDS, 'd', { meta: true })

    expect([...next.selected]).toEqual(['a'])
    expect(next.anchor).toBe('d')
  })

  it('falls back to a plain click when shift has no anchor', () => {
    const next = applyClick({ selected: new Set(), anchor: null }, IDS, 'c', { shift: true })

    expect([...next.selected]).toEqual(['c'])
  })
})
