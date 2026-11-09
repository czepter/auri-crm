export function sendQuoteHref(quote: { id: string; status: string } | null | undefined, canManageQuotes: boolean): string | null {
    return quote?.status === "draft" && canManageQuotes ? `/angebote/${quote.id}/senden` : null;
}
