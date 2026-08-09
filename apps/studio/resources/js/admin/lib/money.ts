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
