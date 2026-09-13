import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Setup from './Setup.vue'

const meta = {
  title: 'Bildschirme/Anmeldung/Erste Einrichtung',
  component: Setup,
  parameters: { layout: 'fullscreen', url: '/einrichtung', page: { auth: { user: null, can: [] } } },
} satisfies Meta<typeof Setup>

export default meta
type Story = StoryObj<typeof meta>

/** Der erste Bildschirm einer leeren Installation: das erste Konto. */
export const ErstesKonto: Story = {}
