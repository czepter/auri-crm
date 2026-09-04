import { describe, expect, it } from 'vitest'
import { keyIntent, stepIndex } from './lightbox'

describe('keyIntent', () => {
  it('closes on Escape even while the comment field has focus', () => {
    expect(keyIntent('Escape', true)).toBe('close')
  })

  it('steps on the arrows when nothing is being typed', () => {
    expect(keyIntent('ArrowLeft', false)).toBe('prev')
    expect(keyIntent('ArrowRight', false)).toBe('next')
  })

  it('leaves the arrows to the comment field while it has focus', () => {
    expect(keyIntent('ArrowLeft', true)).toBeNull()
    expect(keyIntent('ArrowRight', true)).toBeNull()
  })

  it('ignores every other key', () => {
    expect(keyIntent('a', false)).toBeNull()
    expect(keyIntent('Enter', false)).toBeNull()
  })
})

describe('stepIndex', () => {
  it('wraps past the last picture to the first', () => {
    expect(stepIndex(3, 1, 4)).toBe(0)
  })

  it('wraps before the first picture to the last', () => {
    expect(stepIndex(0, -1, 4)).toBe(3)
  })

  it('moves by one in between', () => {
    expect(stepIndex(1, 1, 4)).toBe(2)
    expect(stepIndex(1, -1, 4)).toBe(0)
  })
})
