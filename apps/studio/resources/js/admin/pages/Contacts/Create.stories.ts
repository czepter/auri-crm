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

const SOCIAL_PLATFORMS = [
  { value: 'instagram', label: 'Instagram' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'tiktok', label: 'TikTok' },
  { value: 'pinterest', label: 'Pinterest' },
  { value: 'linkedin', label: 'LinkedIn' },
  { value: 'website', label: 'Website' },
]

const meta = {
  title: 'Bildschirme/Kontakte/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/kontakte/neu' },
  args: { portalDefaults: PORTAL_DEFAULTS, socialPlatforms: SOCIAL_PLATFORMS },
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
