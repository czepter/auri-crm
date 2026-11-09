import { describe, expect, it } from 'vitest'
import { COLOUR_KEYS, columnsOf, complete, DEFAULT_DESIGN, FONTS, styleOf, TYPES, withStyle } from './galleryDesign'

describe('styleOf', () => {
  it('names the four packages', () => {
    expect(styleOf({ type: 'sans', colour: 'hell' })).toBe('klar')
    expect(styleOf({ type: 'serif', colour: 'hell' })).toBe('klassisch')
    expect(styleOf({ type: 'sans', colour: 'sand' })).toBe('warm')
    expect(styleOf({ type: 'sans', colour: 'dunkel' })).toBe('dunkel')
  })

  it('turns "eigene" on as soon as type or colour differ on their own', () => {
    expect(styleOf({ type: 'modern', colour: 'hell' })).toBe('eigene')
    expect(styleOf({ type: 'sans', colour: 'meer' })).toBe('eigene')
  })
})

describe('withStyle', () => {
  it('sets type and colour and keeps the rest', () => {
    const design = { ...DEFAULT_DESIGN, cover: 'rahmen', flow: 'spalten' }

    expect(withStyle(design, 'dunkel')).toEqual({ ...design, type: 'sans', colour: 'dunkel' })
  })

  it('ignores "eigene"', () => {
    expect(withStyle(DEFAULT_DESIGN, 'eigene')).toBe(DEFAULT_DESIGN)
  })
})

describe('complete', () => {
  it('fills missing keys with the defaults', () => {
    expect(complete({ colour: 'meer' })).toEqual({ ...DEFAULT_DESIGN, colour: 'meer' })
    expect(complete(null)).toEqual(DEFAULT_DESIGN)
  })
})

describe('columnsOf', () => {
  it('shrinks to the phone', () => {
    expect(columnsOf('klein', false)).toBe(5)
    expect(columnsOf('klein', true)).toBe(2)
    expect(columnsOf('gross', true)).toBe(1)
  })
})

describe('tokens', () => {
  it('has a font for every type and nine colours', () => {
    expect(TYPES.every((type) => FONTS[type])).toBe(true)
    expect(COLOUR_KEYS).toHaveLength(9)
  })
})
