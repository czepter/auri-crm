import type { Meta, StoryObj } from '@storybook/vue3-vite'
import RecoveryCodes from './RecoveryCodes.vue'

const meta = {
  title: 'Bildschirme/Anmeldung/Wiederherstellungscodes',
  component: RecoveryCodes,
  parameters: { layout: 'fullscreen', url: '/auth/mfa/codes', page: { auth: { user: null, can: [] } } },
  args: {
    codes: [
      'K4P2-9XQD', 'M7RT-1LZB', 'V3HN-8CWK', 'Q9FD-2MJA',
      'T6SX-5RPE', 'B1KY-7NUC', 'Z8GM-3DVH', 'L5WJ-6TQF',
    ],
  },
} satisfies Meta<typeof RecoveryCodes>

export default meta
type Story = StoryObj<typeof meta>

/** Die acht Codes, einmal nach der Einrichtung. */
export const Codes: Story = {}
