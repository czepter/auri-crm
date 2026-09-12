import { describe, expect, it } from 'vitest'
import { applyDiscount, lineAmount, span } from './quoteTotals'

describe('quoteTotals', () => {
  it('rechnet Menge mal Preis', () => {
    expect(lineAmount({ quantity: 2, unitPrice: 69000, discountType: null, discountValue: null })).toBe(138000)
  })

  it('zieht einen Prozentrabatt ab', () => {
    expect(lineAmount({ quantity: 1, unitPrice: 100000, discountType: 'percentage', discountValue: 10 })).toBe(90000)
  })

  it('faellt nie unter null', () => {
    expect(applyDiscount(1000, { type: 'amount', value: 5000 })).toBe(0)
  })

  it('rechnet die Spanne aus Stunden und Zeilen', () => {
    const rows = [
      { quantity: 1, unitPrice: 40000, discountType: null, discountValue: null, selectable: false },
      { quantity: 1, unitPrice: 69000, discountType: null, discountValue: null, selectable: true },
    ]

    expect(span(rows, 3, 25000, false)).toBe(115000)
    expect(span(rows, 12, 25000, true)).toBe(409000)
  })
})
