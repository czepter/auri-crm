import type { Meta, StoryObj } from '@storybook/vue3-vite'
import ForgotPassword from './ForgotPassword.vue'

const meta = {
  title: 'Bildschirme/Anmeldung/Passwort vergessen',
  component: ForgotPassword,
  parameters: { layout: 'fullscreen', url: '/passwort/vergessen', page: { auth: { user: null, can: [] } } },
} satisfies Meta<typeof ForgotPassword>

export default meta
type Story = StoryObj<typeof meta>

/** Das leere Formular. */
export const Leer: Story = {}

/** Nach dem Absenden — dieselbe Meldung, ob es die Adresse gibt oder nicht. */
export const Abgeschickt: Story = {
  parameters: {
    page: {
      auth: { user: null, can: [] },
      flash: { status: 'Wenn ein Konto zu dieser Adresse gehört, ist der Link unterwegs.', secret: null },
    },
  },
}
