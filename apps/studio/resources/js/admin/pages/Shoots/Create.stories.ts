import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'

const meta = {
  title: 'Bildschirme/Shootings/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/shootings/neu' },
  args: {
    project: {
      id: 'p-soehnke',
      name: 'Söhnke, Britta & Nils',
      contactName: 'Britta Söhnke',
      isBooked: false,
      galleryDefaults: { downloads: null, lists: null, comments: null },
    },
    kinds: [
      'Verlobungsshooting',
      'Hochzeitstag',
      'Feier',
      'Portrait',
      'Sonstige',
    ],
  },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Aus der Übersicht geöffnet: erst wenn Projekt oder Anfrage gewählt ist, kommt der Rest. */
export const Auswahl: Story = { args: { project: null } }

/** An einer Anfrage gewählt, bereit für Art, Tag und Ort. */
export const AnAnfrage: Story = {}
