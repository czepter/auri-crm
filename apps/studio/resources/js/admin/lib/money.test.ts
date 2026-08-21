import { describe, expect, it } from 'vitest'
import { centsToEuros, eurosToCents, formatEuros, formatEurosRounded } from './money'

describe('formatting minor units', () => {
  // A price keeps its cents; a headline figure drops them. The two are not
  // interchangeable — a 12,50 € item read as "13 €" is a different price.
  it('keeps cents on a price and drops them on a figure', () => {
    expect(formatEuros(1250)).toBe('12,50\u00a0€')
    expect(formatEurosRounded(1250)).toBe('13\u00a0€')
    expect(formatEurosRounded(440000)).toBe('4.400\u00a0€')
  })
})

describe('the form boundary', () => {
  it('reads a comma as a decimal point and stores minor units', () => {
    expect(eurosToCents('12,50')).toBe(1250)
    expect(eurosToCents('  ')).toBeNull()
    expect(centsToEuros(1250)).toBe('12.50')
    expect(centsToEuros(null)).toBe('')
  })
})
