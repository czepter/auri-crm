import type { ComposerTranslation } from 'vue-i18n'

type TitleSource = Pick<App.Quotes.Data.QuoteData, 'isAddendum' | 'predecessorNumber' | 'number'>

export function quoteTitle(quote: TitleSource, t: ComposerTranslation): string {
  if (quote.isAddendum) {
    return t('quotes.addendumTo', { number: quote.predecessorNumber ?? '' })
  }

  return quote.number ? `${t('quotes.title')} ${quote.number}` : t('quotes.title')
}

/** draft/sent/accepted/superseded/expired -> the mockup's own three chip states (tokens.css). */
export function quoteChipClass(status: string): string {
  return ({ sent: 'now', accepted: 'done' } as Record<string, string>)[status] ?? 'next'
}
