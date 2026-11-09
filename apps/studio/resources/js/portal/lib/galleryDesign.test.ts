import { describe, expect, it } from 'vitest'
import { designAttrs, objectPosition } from './galleryDesign'

describe('galleryDesign', () => {
  it('names one data attribute per design key', () => {
    expect(designAttrs({ cover: 'rahmen', type: 'serif', colour: 'meer', size: 'klein', spacing: 'weit', flow: 'spalten' })).toEqual({
      'data-gcover': 'rahmen',
      'data-gtype': 'serif',
      'data-gcolour': 'meer',
      'data-gsize': 'klein',
      'data-gspacing': 'weit',
      'data-gflow': 'spalten',
    })
  })

  it('positions the cover on the focal point, else the old crop', () => {
    expect(objectPosition({ x: 20, y: 70 })).toBe('20% 70%')
    expect(objectPosition(null)).toBe('50% 38%')
  })
})
