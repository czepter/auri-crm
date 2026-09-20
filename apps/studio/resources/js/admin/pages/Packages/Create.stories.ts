import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'

const meta = {
  title: 'Bildschirme/Pakete/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/pakete/neu' },
  args: {
    catalogItems: [
      { id: 'ci-1', title: 'Editierte Datei extra', description: null, unitPrice: 2500 },
      { id: 'ci-2', title: 'Drohnenaufnahmen', description: null, unitPrice: 9000 },
      { id: 'ci-3', title: 'Express-Bearbeitung (48h)', description: null, unitPrice: 6000 },
    ],
  },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Das leere Formular: Name, Beschreibung und eine Positionsliste ohne Zeile. */
export const Leer: Story = {}
