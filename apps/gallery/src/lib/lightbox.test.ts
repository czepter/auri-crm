import { describe, expect, it } from 'vitest'
import { keyIntent, stepIndex } from './lightbox'

describe('keyIntent', () => {
  it('closes on Escape even while the comment field has focus', () => {
    expect(keyIntent('Escape', true)).toBe('close')
  })

  it('steps on the arrows when the guest is not typing', () => {
    expect(keyIntent('ArrowLeft', false)).toBe('prev')
    expect(keyIntent('ArrowRight', false)).toBe('next')
  })

  it('leaves the arrows to the comment field while it has focus', () => {
    expect(keyIntent('ArrowLeft', true)).toBeNull()
    expect(keyIntent('ArrowRight', true)).toBeNull()
  })

  it('ignores every other key', () => {
    expect(keyIntent('a', false)).toBeNull()
  })
})

describe('stepIndex', () => {
  it('wraps in both directions', () => {
    expect(stepIndex(3, 1, 4)).toBe(0)
    expect(stepIndex(0, -1, 4)).toBe(3)
  })
})
