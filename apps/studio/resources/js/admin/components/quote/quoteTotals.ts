export type QuoteLineInput = {
  quantity: number
  unitPrice: number
  discountType: 'percentage' | 'amount' | null
  discountValue: number | null
}

export type QuoteSpanRow = QuoteLineInput & { selectable: boolean }

/**
 * A plain percent here (10 means 10 %), not the basis points quotes carry
 * server-side — this module is pure and stays independent of that encoding.
 */
export function applyDiscount(amount: number, discount: { type: 'percentage' | 'amount' | null; value: number | null }): number {
  if (discount.type === 'percentage' && discount.value !== null) {
    return Math.max(0, amount - Math.round((amount * discount.value) / 100))
  }

  if (discount.type === 'amount' && discount.value !== null) {
    return Math.max(0, amount - discount.value)
  }

  return Math.max(0, amount)
}

export function lineAmount(row: QuoteLineInput): number {
  return applyDiscount(row.quantity * row.unitPrice, { type: row.discountType, value: row.discountValue })
}

export function subtotal(rows: QuoteLineInput[], timeTotal: number): number {
  return timeTotal + rows.reduce((sum, row) => sum + lineAmount(row), 0)
}

/**
 * The three range tiles all call this: pass the full row set with
 * withOptional=true for the largest end, only the non-selectable rows with
 * withOptional=false for the smallest, and a pre-filtered (selected) row set
 * with withOptional=true for the preselection — so all three stay comparable.
 */
export function span(rows: QuoteSpanRow[], hours: number, rate: number, withOptional: boolean): number {
  return hours * rate + rows.reduce((sum, row) => (row.selectable && !withOptional ? sum : sum + lineAmount(row)), 0)
}
