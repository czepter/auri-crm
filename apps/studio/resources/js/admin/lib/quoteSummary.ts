/**
 * Was den Preis macht, steht oben — nicht was zuerst getippt wurde. Die
 * Übersicht beantwortet „wofür", das Angebot beantwortet „in welcher
 * Reihenfolge".
 */
export function topLines<T extends { netTotal: number }>(items: T[], limit = 3): { shown: T[]; rest: number } {
  const shown = [...items].sort((a, b) => b.netTotal - a.netTotal).slice(0, limit)

  return { shown, rest: Math.max(0, items.length - shown.length) }
}

export function grossTotal(quote: { net: number; vatAmount: number; vatMode: string }): number {
  return quote.vatMode === 'standard' ? quote.net + quote.vatAmount : quote.net
}
