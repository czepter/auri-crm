/*
 * Die Saison liest in Kalenderwochen, Montag bis Sonntag, wie der Monat.
 * ISO-Wochen, damit jede Woche am Jahreswechsel genau einem Jahr gehört —
 * sonst stünde die Woche um Neujahr in beiden Zeilen.
 */

export function addDays(iso: string, days: number): string {
  const date = new Date(`${iso}T00:00:00Z`)
  date.setUTCDate(date.getUTCDate() + days)

  return date.toISOString().slice(0, 10)
}

function week1Monday(year: number): string {
  const jan4 = new Date(Date.UTC(year, 0, 4))
  jan4.setUTCDate(jan4.getUTCDate() - ((jan4.getUTCDay() + 6) % 7))

  return jan4.toISOString().slice(0, 10)
}

/** Die Montage aller ISO-Wochen eines Jahres, 52 oder 53. */
export function weekMondays(year: number): string[] {
  const end = week1Monday(year + 1)
  const mondays: string[] = []

  for (let monday = week1Monday(year); monday < end; monday = addDays(monday, 7)) mondays.push(monday)

  return mondays
}

/**
 * Welche Jahre die Saison zeigen kann: zurück bis zum ersten Jahr mit Termin,
 * vor bis ein Jahr nach dem letzten. Das laufende und das nächste Jahr immer.
 */
export function seasonYears(dates: string[], today: string): { first: number; last: number } {
  const now = Number(today.slice(0, 4))
  const years = dates.map((date) => Number(date.slice(0, 4)))

  return {
    first: Math.min(now, ...years),
    last: Math.max(now + 1, ...years.map((year) => year + 1)),
  }
}
