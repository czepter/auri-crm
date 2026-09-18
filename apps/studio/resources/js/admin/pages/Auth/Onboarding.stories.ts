import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Onboarding from './Onboarding.vue'
import { studioSettings as settings } from '../__stories__/fixtures'

const meta = {
  title: 'Bildschirme/Anmeldung/Einrichtung Studio',
  component: Onboarding,
  parameters: { layout: 'fullscreen', url: '/einrichtung/studio', page: { brand: 'Auri CRM' } },
  args: { settings, emailVerified: true, hasLiveVerification: true },
} satisfies Meta<typeof Onboarding>

export default meta
type Story = StoryObj<typeof meta>

/** Der ganze Assistent: was der Kunde im Portal zuerst sieht. */
export const Marke: Story = {}

/** Derselbe Bildschirm, solange die Adresse noch auf ihren Code wartet. */
export const MarkeUnbestaetigt: Story = { args: { emailVerified: false } }

/** Mit hinterlegtem Logo zeichnet die Vorschau die Datei statt der Initiale. */
export const MitLogo: Story = {
  args: { settings: { ...settings, logoUrl: '/logo.png', accentColor: '#1C6870' } },
}
