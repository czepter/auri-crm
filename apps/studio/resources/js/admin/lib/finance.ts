/** Anschaffungskosten bis zu dieser Grenze (Cent) dürfen sofort abgeschrieben werden. */
export const GWG_LIMIT = 80_000

export type ShareKind = 'voll' | 'keiner' | 'anteilig'

export function shareKind(share: number): ShareKind {
  return share >= 100 ? 'voll' : share <= 0 ? 'keiner' : 'anteilig'
}

/** Kleinunternehmer schreiben vom Brutto ab, alle anderen vom Netto. */
export function costBasis(vatMode: string, net: number, gross: number): number {
  return vatMode === 'small_business' ? gross : net
}

export function defaultMethod(basis: number): 'gwg' | 'linear' {
  return basis <= GWG_LIMIT ? 'gwg' : 'linear'
}

export function modeForYear(modes: Record<string, string>, fallback: string, year: number): string {
  return modes[String(year)] ?? fallback
}

/** Netto und USt. aus Brutto und Satz (Basispunkte), kaufmännisch gerundet wie auf dem Server. */
export function splitGross(gross: number, rate: number): { net: number; vat: number } {
  const vat = Math.floor((gross * rate * 2 + (10_000 + rate)) / ((10_000 + rate) * 2))

  return { net: gross - vat, vat }
}

/** Ein Betragsfeld in Cent: "1.725,50", "1725,50" und "1725.50" sind dasselbe. Leer ist null. */
export function parseCents(value: string): number | null {
  const trimmed = value.trim()

  if (trimmed === '') return null

  const number = parseFloat(trimmed.includes(',') ? trimmed.replace(/\./g, '').replace(',', '.') : trimmed)

  return Number.isNaN(number) ? null : Math.round(number * 100)
}

/** Cent als Feldinhalt mit Komma, ohne Tausenderpunkt. */
export function centsToField(cents: number): string {
  return (cents / 100).toFixed(2).replace('.', ',')
}
