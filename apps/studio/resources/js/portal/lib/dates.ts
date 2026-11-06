/** "26.12.2026". Parsed at noon: new Date('2026-12-26') reads UTC and slips to the day before west of it. */
export function shortDate(iso: string): string {
  return new Date(`${iso.slice(0, 10)}T12:00:00`).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

/** "Samstag, 30. Mai 2026". */
export function longDate(iso: string): string {
  return new Date(`${iso.slice(0, 10)}T12:00:00`).toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

/** "Sa., 30.05.2026": the one date format on every portal screen. */
export function dayDate(iso: string): string {
  return new Date(`${iso.slice(0, 10)}T12:00:00`).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' })
}

/** Whole calendar days from today to `iso`; negative once it has passed. */
export function daysUntil(iso: string, now: Date = new Date()): number {
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const day = new Date(`${iso.slice(0, 10)}T00:00:00`)

  return Math.round((day.getTime() - today.getTime()) / 86_400_000)
}
