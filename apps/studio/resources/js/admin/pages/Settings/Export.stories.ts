import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Export from './Export.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Export',
  component: Export,
  parameters: { layout: 'fullscreen', url: '/einstellungen/export' },
  args: { contacts: 121, projects: 38 },
} satisfies Meta<typeof Export>

export default meta
type Story = StoryObj<typeof meta>

export const Laufend: Story = {}
