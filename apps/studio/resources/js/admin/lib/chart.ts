/**
 * Die zwei Rechnungen hinter den Balken der Auswertungen. Sie stehen hier und
 * nicht in der Seite, weil ein Monat ohne Quote (keine Anfrage im Monat) die
 * Linie unterbrechen würde und das ohne Test niemand sieht.
 */

/** Balkenhöhe in px, gemessen am größten Wert der Reihe. */
export function barHeight(value: number, peak: number, max: number): string {
  return `${Math.round((value / Math.max(1, peak)) * max)}px`
}

/**
 * Punkte der Quotenlinie in einem viewBox von 1000 × 216. Monate ohne Quote
 * liefern keinen Punkt: die Linie überspringt sie, statt auf null zu fallen.
 */
export function ratePoints(rates: (number | null)[], scale: number): string {
  const slot = 1000 / Math.max(1, rates.length)

  return rates
    .map((rate, index) =>
      rate === null
        ? null
        : `${(index * slot + slot / 2).toFixed(1)},${(206 - (rate / scale) * 186).toFixed(1)}`,
    )
    .filter((point): point is string => point !== null)
    .join(' ')
}
