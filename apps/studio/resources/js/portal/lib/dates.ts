/** "26.12.2026". Parsed at noon: new Date('2026-12-26') reads UTC and slips to the day before west of it. */
export function shortDate(iso: string): string {
  return new Date(`${iso.slice(0, 10)}T12:00:00`).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
