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
      { value: 'engagement', label: 'Verlobungsshooting' },
      { value: 'wedding', label: 'Hochzeitstag' },
      { value: 'reception', label: 'Feier' },
      { value: 'portrait', label: 'Portrait' },
      { value: 'other', label: 'Sonstige' },
    ],
    conflicts: [{ label: 'Gebucht — Hochzeit Rahn · Gut Basthorst ›', href: '/auftraege/p-rahn/shootings' }],
  },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Aus der Übersicht geöffnet: erst wenn Projekt oder Anfrage gewählt ist, kommt der Rest. */
export const Auswahl: Story = { args: { project: null, conflicts: [] } }

/** An einer Anfrage, deren Datum schon gebucht ist. */
export const AnAnfrage: Story = {}
