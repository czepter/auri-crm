import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Calendar from './Calendar.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Kalender',
  component: Calendar,
  parameters: { layout: 'fullscreen', url: '/einstellungen/kalender' },
  args: {
    feedUrl: 'https://atelier-nordlicht.de/kalender/8f3c1a9e27b4/feed.ics',
  },
} satisfies Meta<typeof Calendar>

export default meta
type Story = StoryObj<typeof meta>

/** Die Abo-Adresse des Termin-Feeds. */
export const Abonnieren: Story = {}
