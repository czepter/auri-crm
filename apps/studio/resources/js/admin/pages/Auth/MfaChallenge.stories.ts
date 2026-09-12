import type { Meta, StoryObj } from '@storybook/vue3-vite'
import MfaChallenge from './MfaChallenge.vue'

const meta = {
  title: 'Bildschirme/Anmeldung/Zwei-Faktor Abfrage',
  component: MfaChallenge,
  parameters: { layout: 'fullscreen', url: '/auth/mfa', page: { auth: { user: null, can: [] } } },
} satisfies Meta<typeof MfaChallenge>

export default meta
type Story = StoryObj<typeof meta>

/** Nach Benutzername und Passwort: nur noch der Code. */
export const Abfrage: Story = {}
