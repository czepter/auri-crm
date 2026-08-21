/*
 * Datumsfelder haben zwei Leser: das Feld will YYYY-MM-DD, der
 * Mensch davor will "in 3 Wochen". Beides hier, weil die Fallen (Zeitzone,
 * Monatsende) sonst in jeder Komponente einzeln stehen.
 */

/** YYYY-MM-DD in Ortszeit — sv-SE druckt genau dieses Format, toISOString() rutscht vor 02:00 Uhr auf den Vortag. */
export function isoDate(date: Date): string {
  return date.toLocaleDateString('sv-SE')
}

/** Frist ab einem Tag: Wochen über Tage, Monate über setMonth, damit "3 Mon" auf denselben Kalendertag fällt. */
export function shiftDate(from: Date, span: { weeks?: number; months?: number }): string {
  const date = new Date(from)
  if (span.weeks) date.setDate(date.getDate() + span.weeks * 7)
  if (span.months) date.setMonth(date.getMonth() + span.months)

  return isoDate(date)
}

/**
 * Tage bis zu einem YYYY-MM-DD. NaN, solange das Feld leer oder halb getippt
 * ist — die Form wird geprüft, weil Date.parse('2026-08-') den Ersten des
 * Monats zurückgibt statt aufzugeben.
 */
export function daysUntil(iso: string, from = new Date()): number {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) return Number.NaN

  return Math.round((Date.parse(iso) - Date.UTC(from.getFullYear(), from.getMonth(), from.getDate())) / 86_400_000)
}

/** Dasselbe Datum als Abstand: "in 5 Tagen", "in 3 Wochen", "vor 2 Monaten". Leer, wenn nichts Gültiges dasteht. */
export function relativeDate(iso: string, locale: string, from = new Date()): string {
  const days = daysUntil(iso, from)
  if (Number.isNaN(days)) return ''

  const format = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' })
  if (Math.abs(days) < 14) return format.format(days, 'day')
  if (Math.abs(days) < 60) return format.format(Math.round(days / 7), 'week')

  return format.format(Math.round(days / 30.44), 'month')
}

/**
 * "Mo., 14.06." — die Datumsspalte der Fallschirme und des Fadens. Ohne Datum
 * steht der Platzhalter, den der Schirm dort führt.
 */
export function dayWithWeekday(iso: string | null | undefined, fallback: string | null = '—'): string | null {
  return iso
    ? new Date(iso).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' })
    : fallback
}
