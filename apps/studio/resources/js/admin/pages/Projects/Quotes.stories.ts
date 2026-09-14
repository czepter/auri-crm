import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Quotes from './Quotes.vue'
import { header, quote } from '../__stories__/fixtures'

/** Die Fassung vor der Zweitfotografin — angenommen wurde die nächste. */
const ersetzt: App.Quotes.Data.QuoteData = {
    ...quote,
    id: 'q-rahn-v1',
    number: '2026-0028',
    status: 'superseded',
    statusLabel: 'Ersetzt',
    sentAt: '2026-02-18T14:20:00+01:00',
    net: 360000,
    total: 360000,
    items: quote.items.slice(0, 1),
    paymentStages: [],
}

const angenommen: App.Quotes.Data.QuoteData = {
    ...quote,
    predecessorId: ersetzt.id,
    predecessorNumber: ersetzt.number,
}

const meta = {
    title: 'Bildschirme/Auftrag Angebote',
    component: Quotes,
    parameters: { layout: 'fullscreen', url: `/auftraege/${header.id}/angebote` },
    args: {
        header,
        quotes: [angenommen, ersetzt],
        payments: {
            number: angenommen.number,
            rows: [
                { label: 'Bei der Zusage', amount: 120000 },
                { label: 'Nach der Galerie', amount: 300000 },
            ],
        },
    },
} satisfies Meta<typeof Quotes>

export default meta
type Story = StoryObj<typeof meta>

/** Angebot 2026-0031 ist angenommen, 2026-0028 wurde davon ersetzt. */
export const Angenommen: Story = {}

/** Gebucht, aber noch ohne Angebot im Auftrag. */
export const OhneAngebot: Story = {
    args: { quotes: [], payments: null },
}
