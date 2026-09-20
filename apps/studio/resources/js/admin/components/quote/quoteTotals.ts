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

/** Server-side encoding (basis points / cents) into applyDiscount's plain percent. */
export function quoteDiscount(type: string | null, value: number | null): { type: 'percentage' | 'amount' | null; value: number | null } {
  if (type !== 'percentage' && type !== 'amount') {
    return { type: null, value: null }
  }

  return { type, value: type === 'percentage' && value !== null ? value / 100 : value }
}

/** Mirrors DiscountCalculator::spread(): total pro rata over amounts, leftover cent onto the largest. */
export function spread(amounts: number[], total: number): number[] {
  const base = amounts.reduce((sum, amount) => sum + amount, 0)

  if (base <= 0) {
    return amounts
  }

  const shares = amounts.map((amount) => Math.floor((total * amount) / base))
  shares[amounts.indexOf(Math.max(...amounts))] += total - shares.reduce((sum, share) => sum + share, 0)

  return shares
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

/**
 * Mirrors App\Quotes\Support\DiscountCalculator::resolve() for a percentage
 * charge rather than a discount — same round-half-up, same [0, base] clamp —
 * so Quote::computeVat()'s server-side amount and this preview never diverge
 * by a cent. `rate` is basis points (1900 = 19 %), matching computeVat().
 */
export function vatAmount(net: number, rateBasisPoints: number): number {
  if (net <= 0 || rateBasisPoints <= 0) {
    return 0
  }

  const amount = Math.floor((net * rateBasisPoints + 5000) / 10000)

  return Math.max(0, Math.min(amount, net))
}

/**
 * Mirrors App\Quotes\Support\VatLines::fromItems() — the quote-level discount
 * shared over the rates pro rata, the leftover cent onto the largest line, so
 * the live preview prints the same per-rate rows the PDF does.
 */
export function vatLines(
  rows: { netTotal: number; vatRate: number | null }[],
  defaultRate: number,
  net: number,
): { rate: number; net: number; vat: number }[] {
  const bases = new Map<number, number>()

  for (const row of rows) {
    if (row.netTotal === 0) {
      continue
    }

    const rate = row.vatRate ?? defaultRate
    bases.set(rate, (bases.get(rate) ?? 0) + row.netTotal)
  }

  if (bases.size === 0) {
    return [{ rate: defaultRate, net, vat: vatAmount(net, defaultRate) }]
  }

  const ordered = [...bases.entries()].sort((a, b) => b[1] - a[1])
  const subtotal = ordered.reduce((sum, [, base]) => sum + base, 0)

  const lines = ordered.map(([rate, base]) => {
    const share = subtotal === 0 ? 0 : Math.floor((net * base) / subtotal)

    return { rate, net: share, vat: 0 }
  })

  const spent = lines.reduce((sum, line) => sum + line.net, 0)
  lines[0].net += net - spent

  return lines.map((line) => ({ ...line, vat: vatAmount(line.net, line.rate) }))
}
