import { describe, expect, it } from 'vitest'
import { applyDiscount, lineAmount, span, spread, vatLines } from './quoteTotals'

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

  // #412: spiegelt App\Quotes\Support\VatLines::fromItems() — der
  // Angebotsrabatt anteilig, der Restcent auf die groesste Zeile.
  it('teilt das Netto je Steuersatz auf, groesste Zeile zuerst', () => {
    const rows = [
      { netTotal: 100000, vatRate: null },
      { netTotal: 10000, vatRate: 700 },
    ]

    expect(vatLines(rows, 1900, 110000)).toEqual([
      { rate: 1900, net: 100000, vat: 19000 },
      { rate: 700, net: 10000, vat: 700 },
    ])
  })

  it('legt den Rundungsrest auf die groesste Zeile, damit die Summe stimmt', () => {
    const rows = [
      { netTotal: 100000, vatRate: null },
      { netTotal: 33333, vatRate: 700 },
    ]
    const lines = vatLines(rows, 1900, 99999)

    expect(lines.reduce((sum, line) => sum + line.net, 0)).toBe(99999)
    expect(lines[0].rate).toBe(1900)
  })

  it('nennt den Angebotssatz, wo keine Zeile einen eigenen traegt', () => {
    expect(vatLines([], 1900, 50000)).toEqual([{ rate: 1900, net: 50000, vat: 9500 }])
  })
})

describe('spread', () => {
  it('teilt den Betrag anteilig und legt den Rest-Cent auf den größten', () => {
    expect(spread([25000, 20000], 37000)).toEqual([20556, 16444])
  })
})
