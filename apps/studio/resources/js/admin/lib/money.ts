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
