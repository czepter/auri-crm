import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Payments from './Payments.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Zahlungen',
  component: Payments,
  parameters: { layout: 'fullscreen', url: '/einstellungen/zahlungen' },
  args: {
    settings: {
      id: 'ps1',
      stripeEnabled: true,
      stripeMode: 'live',
      hasStripeTestKeys: true,
      hasStripeLiveKeys: true,
    },
    status: { lastWebhookAt: '2026-09-20T18:12:00+02:00', lastPaidOn: '2026-09-18' },
  },
} satisfies Meta<typeof Payments>

export default meta
type Story = StoryObj<typeof meta>

/** Stripe im Live-Modus, Erinnerungen laufen. */
export const Live: Story = {}

/** Noch keine Schlüssel hinterlegt. */
export const OhneStripe: Story = {
  args: {
    settings: {
      id: 'ps1',
      stripeEnabled: false,
      stripeMode: 'test',
      hasStripeTestKeys: false,
      hasStripeLiveKeys: false,
    },
  },
}
