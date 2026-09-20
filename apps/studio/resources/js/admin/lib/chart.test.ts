import { describe, expect, it } from 'vitest'
import { barHeight, ratePoints } from './chart'

describe('barHeight', () => {
  it('scales against the tallest bar', () => {
    expect(barHeight(21, 21, 188)).toBe('188px')
    expect(barHeight(0, 21, 188)).toBe('0px')
  })

  it('survives an empty window, where the peak is zero', () => {
    expect(barHeight(0, 0, 188)).toBe('0px')
  })
})

describe('ratePoints', () => {
  it('puts a point in the middle of each month slot', () => {
    expect(ratePoints([50, 25], 50)).toBe('250.0,20.0 750.0,113.0')
  })

  it('skips a month without a rate instead of dropping the line to zero', () => {
    const points = ratePoints([50, null, 50], 50).split(' ')

    expect(points).toHaveLength(2)
    expect(points[1]).toBe('833.3,20.0')
  })

  it('returns nothing when no month has a rate', () => {
    expect(ratePoints([null, null], 50)).toBe('')
  })
})
