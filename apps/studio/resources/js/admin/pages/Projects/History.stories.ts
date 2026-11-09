import type { Meta, StoryObj } from '@storybook/vue3-vite'
import History from './History.vue'
import { header, thread } from '../__stories__/fixtures'

const meta = {
  title: 'Bildschirme/Verlauf',
  component: History,
  parameters: { layout: 'fullscreen', url: `/auftraege/${header.id}/verlauf` },
  args: { header, thread },
} satisfies Meta<typeof History>

export default meta
type Story = StoryObj<typeof meta>

export const Verlauf: Story = {}
