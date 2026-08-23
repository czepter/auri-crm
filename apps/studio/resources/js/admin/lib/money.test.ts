import { describe, expect, it } from 'vitest'
import { formatDiscount, parseDiscount } from './money'

describe('parseDiscount', () => {
  it('reads a trailing percent sign as a percentage in basis points', () => {
    expect(parseDiscount('10%')).toEqual({ type: 'percentage', value: 1000 })
    expect(parseDiscount('10 %')).toEqual({ type: 'percentage', value: 1000 })
    expect(parseDiscount('10,5%')).toEqual({ type: 'percentage', value: 1050 })
  })

  it('reads everything else as an amount in cents', () => {
    expect(parseDiscount('50')).toEqual({ type: 'amount', value: 5000 })
    expect(parseDiscount('50,00')).toEqual({ type: 'amount', value: 5000 })
  })

  it('reads an empty cell as no discount', () => {
    expect(parseDiscount('')).toEqual({ type: '', value: null })
    expect(parseDiscount('   ')).toEqual({ type: '', value: null })
  })

  it('reads what is not a number as no discount rather than as NaN', () => {
    expect(parseDiscount('abc')).toEqual({ type: '', value: null })
    expect(parseDiscount('%')).toEqual({ type: '', value: null })
  })
})

describe('formatDiscount', () => {
  it('round-trips through parseDiscount', () => {
    expect(parseDiscount(formatDiscount('percentage', 1000))).toEqual({ type: 'percentage', value: 1000 })
    expect(parseDiscount(formatDiscount('amount', 5000))).toEqual({ type: 'amount', value: 5000 })
  })

  it('renders no discount as an empty cell', () => {
    expect(formatDiscount(null, null)).toBe('')
    expect(formatDiscount('percentage', null)).toBe('')
  })
})
