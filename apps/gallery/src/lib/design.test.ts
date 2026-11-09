import { describe, expect, it } from 'vitest'
import { designAttrs, GATE_DESIGN, objectPosition } from './design'

describe('designAttrs', () => {
  it('names one data attribute per design key', () => {
    expect(designAttrs({ ...GATE_DESIGN, colour: 'meer', flow: 'spalten' })).toEqual({
      'data-gcover': 'zentriert',
      'data-gtype': 'sans',
      'data-gcolour': 'meer',
      'data-gsize': 'mittel',
      'data-gspacing': 'eng',
      'data-gflow': 'spalten',
    })
  })
})

describe('objectPosition', () => {
  it('uses the focal point and falls back to the old crop', () => {
    expect(objectPosition({ x: 20, y: 70 })).toBe('20% 70%')
    expect(objectPosition(null)).toBe('50% 38%')
  })
})
