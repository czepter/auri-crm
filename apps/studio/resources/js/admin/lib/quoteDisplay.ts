import type { ComposerTranslation } from 'vue-i18n'

type TitleSource = Pick<App.Quotes.Data.QuoteData, 'isAddendum' | 'predecessorNumber' | 'number'>

export function quoteTitle(quote: TitleSource, t: ComposerTranslation): string {
  if (quote.isAddendum) {
    return t('quotes.addendumTo', { number: quote.predecessorNumber ?? '' })
  }

  return quote.number ? `${t('quotes.title')} ${quote.number}` : t('quotes.title')
}

/** The one status colour map for quotes; `sent` is blue so it never shares booked's amber. */
export function quoteChipClass(status: string): string {
  return ({ sent: 'sent', accepted: 'done', superseded: 'off', expired: 'fail' } as Record<string, string>)[status] ?? 'next'
}
