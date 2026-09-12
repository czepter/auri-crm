import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'

const meta = {
  title: 'Bildschirme/Kontakte/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/kontakte/neu' },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Leeres Formular: Name, Erreichbarkeit und Anschrift auf einem Bildschirm. */
export const Leer: Story = {}
