/**
 * The catalog stores a price in minor units (integer cents) and never as a
 * float — see PackageItem::$unit_price. These two are the only place that
 * boundary is crossed, so a euro string never reaches the form's own state.
 */

/** A form field's euro string into minor units, or null for "included". */
export function eurosToCents(value: string): number | null {
  const trimmed = value.trim().replace(',', '.')

  return trimmed === '' ? null : Math.round(parseFloat(trimmed) * 100)
}

/** The inverse, for pre-filling a form from a stored minor-unit price. */
export function centsToEuros(cents: number | null): string {
  return cents === null ? '' : (cents / 100).toFixed(2)
}

/** Minor units, formatted for display — e.g. 440000 -> "4.400,00 €" (Q-02's quote table). */
export function formatEuros(cents: number): string {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(cents / 100)
}

/** The same money as a headline figure — tiles, column sums, report rows, where cents are noise. */
export function formatEurosRounded(cents: number): string {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })
    .format(cents / 100)
}

/**
 * A discount percentage is stored server-side as basis points (0–10000, two
 * decimal places — see App\Quotes\Enums\DiscountType) so the same "never a
 * float, never rounded until the total" rule that governs money holds for
 * percentages too. These two are the only place that boundary is crossed.
 */
export function percentToBasisPoints(value: string): number | null {
  const trimmed = value.trim().replace(',', '.')

  return trimmed === '' ? null : Math.round(parseFloat(trimmed) * 100)
}

export function basisPointsToPercent(bps: number | null): string {
  return bps === null ? '' : (bps / 100).toFixed(2)
}

/** For reading, not for a form field: `19`, `12,5`. */
export function formatPercent(bps: number): string {
  return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 2 }).format(bps / 100)
}

export type DiscountField = { type: '' | 'percentage' | 'amount'; value: number | null }

/**
 * Eine Zelle statt Auswahlfeld plus Wertfeld. Das Prozentzeichen ist die
 * Angabe der Einheit, nicht Zierde — deshalb entscheidet es über den Typ.
 */
export function parseDiscount(input: string): DiscountField {
  const trimmed = input.trim()

  if (trimmed === '') {
    return { type: '', value: null }
  }

  const percent = trimmed.endsWith('%')
  const number = percent ? trimmed.slice(0, -1) : trimmed
  const parsed = percent ? percentToBasisPoints(number) : eurosToCents(number)

  return parsed === null || Number.isNaN(parsed)
    ? { type: '', value: null }
    : { type: percent ? 'percentage' : 'amount', value: parsed }
}

export function formatDiscount(type: string | null, value: number | null): string {
  if (!type || value === null) {
    return ''
  }

  return type === 'percentage' ? `${basisPointsToPercent(value)} %` : centsToEuros(value)
}
