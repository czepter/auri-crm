import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Notes from './Notes.vue'
import { header, thread } from '../__stories__/fixtures'

const meta = {
  title: 'Bildschirme/Notizen',
  component: Notes,
  parameters: { layout: 'fullscreen', url: `/auftraege/${header.id}/notizen` },
  args: { header, thread },
} satisfies Meta<typeof Notes>

export default meta
type Story = StoryObj<typeof meta>

export const Notizen: Story = {}
