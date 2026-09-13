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
