import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'

const PORTAL_DEFAULTS: Record<string, string | boolean | null> = {
  portal_visible: true,
  accept_quotes: true,
  payment_status: false,
  gallery_downloads: true,
  gallery_lists: true,
  gallery_comments: false,
  messaging_mode: 'booking',
}

const meta = {
  title: 'Bildschirme/Kontakte/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/kontakte/neu' },
  args: { portalDefaults: PORTAL_DEFAULTS },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Leeres Formular: Name, Erreichbarkeit, Anschrift und Portal auf einem Bildschirm. */
export const Leer: Story = {}

/** Ein Studio, das Angebote im Portal ausgeschaltet hat. */
export const OhnePortalVorgaben: Story = {
  args: {
    portalDefaults: { ...PORTAL_DEFAULTS, accept_quotes: false, portal_visible: false },
  },
}
