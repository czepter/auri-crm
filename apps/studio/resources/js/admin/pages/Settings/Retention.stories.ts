import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Retention from './Retention.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Aufbewahrung',
  component: Retention,
  parameters: { layout: 'fullscreen', url: '/einstellungen/aufbewahrung' },
  args: { months: null },
} satisfies Meta<typeof Retention>

export default meta
type Story = StoryObj<typeof meta>

export const Aus: Story = {}

export const Aktiv: Story = { args: { months: 24 } }
