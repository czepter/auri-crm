import type { Meta, StoryObj } from '@storybook/vue3-vite'
import PortalContact from './PortalContact.vue'
import { branding, settings } from './Portal.stories'

const meta = {
  title: 'Bildschirme/Einstellungen/Portal-Kontakt',
  component: PortalContact,
  parameters: { layout: 'fullscreen', url: '/einstellungen/portal/kontakt' },
  args: { settings, branding },
} satisfies Meta<typeof PortalContact>

export default meta
type Story = StoryObj<typeof meta>

export const Ausgefuellt: Story = {}

/** Noch niemand eingetragen: der Kunde sieht die Marke und sonst nichts. */
export const Leer: Story = {
  args: {
    settings: {
      ...settings,
      contactName: null,
      contactAvatarUrl: null,
      contactPhone: null,
      contactEmail: null,
      contactResponseTime: null,
    },
  },
}
