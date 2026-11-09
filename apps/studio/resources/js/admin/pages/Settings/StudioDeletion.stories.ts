import type { Meta, StoryObj } from '@storybook/vue3-vite'
import StudioDeletion from './StudioDeletion.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Studio löschen',
  component: StudioDeletion,
  parameters: { layout: 'fullscreen', url: '/einstellungen/studio-loeschen' },
  args: { codeSent: false, graceDays: 30, counts: { projects: 38, contacts: 121, galleries: 24, files: 5120 } },
} satisfies Meta<typeof StudioDeletion>

export default meta
type Story = StoryObj<typeof meta>

export const Ausgangszustand: Story = {}

export const CodeGesendet: Story = { args: { codeSent: true } }

export const FalscherCode: Story = {
  args: { codeSent: true },
  parameters: { page: { errors: { code: 'Dieser Code ist nicht gültig.' } } },
}

export const Vorgemerkt: Story = {
  parameters: { page: { studioDeletion: '21.10.2026' } },
}
