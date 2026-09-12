import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'

const meta = {
  title: 'Bildschirme/Pakete/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/pakete/neu' },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Das leere Formular: Name, Beschreibung und eine Positionsliste ohne Zeile. */
export const Leer: Story = {}
