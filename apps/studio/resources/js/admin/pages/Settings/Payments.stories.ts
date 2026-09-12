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
      remindersEnabled: true,
      depositPercent: 30,
      zahlungszielDays: 14,
    },
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
      remindersEnabled: false,
      depositPercent: 30,
      zahlungszielDays: 14,
    },
  },
}
