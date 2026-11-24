import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Contracts from './Contracts.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Verträge',
  component: Contracts,
  parameters: { layout: 'fullscreen', url: '/einstellungen/vertraege' },
  args: { settings: { mode: 'on_demand', signOnline: true } },
} satisfies Meta<typeof Contracts>

export default meta
type Story = StoryObj<typeof meta>

export const BeiBedarf: Story = {}

export const Nie: Story = { args: { settings: { mode: 'off', signOnline: false } } }
