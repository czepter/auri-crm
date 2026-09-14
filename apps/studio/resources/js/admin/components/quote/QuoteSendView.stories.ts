import type { Meta, StoryObj } from '@storybook/vue3-vite'
import QuoteSendView from './QuoteSendView.vue'
import { leadQuote } from '../../pages/__stories__/fixtures'

const meta = {
    title: 'Angebote/Angebot senden',
    component: QuoteSendView,
    parameters: { layout: 'fullscreen' },
    args: {
        quote: leadQuote,
        studioName: 'Studio Nordlicht',
        recipientName: 'Lena Brandt',
        recipientEmail: 'lena.brandt@web.de',
    },
} satisfies Meta<typeof QuoteSendView>

export default meta
type Story = StoryObj<typeof meta>

export const Standard: Story = {}

/** Ohne Gültigkeitsdatum entfällt die Zeile in der Angebotskarte. */
export const OhneGueltigkeit: Story = {
    args: { quote: { ...leadQuote, validUntil: null } },
}

/** Regelbesteuertes Studio: die Angebotskarte trägt USt. und Endsumme wie das Angebot selbst. */
export const MitMehrwertsteuer: Story = {
    args: {
        quote: {
            ...leadQuote,
            vatMode: 'standard',
            vatRate: 1900,
            vatAmount: Math.round(leadQuote.net * 0.19),
        },
    },
}
