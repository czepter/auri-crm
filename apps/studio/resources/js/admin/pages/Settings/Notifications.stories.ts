import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Notifications from './Notifications.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Benachrichtigungen',
  component: Notifications,
  parameters: { layout: 'fullscreen', url: '/einstellungen/benachrichtigungen' },
  args: {
    quietHoursEnabled: true,
    quietHoursStart: '21:00',
    quietHoursEnd: '08:00',
  },
} satisfies Meta<typeof Notifications>

export default meta
type Story = StoryObj<typeof meta>

/** Ruhezeit von 21 bis 8 Uhr. */
export const Ruhezeit: Story = {}

/** Ohne Ruhezeit: die Zeitfelder stehen leer. */
export const Aus: Story = {
  args: { quietHoursEnabled: false, quietHoursStart: null, quietHoursEnd: null },
}
