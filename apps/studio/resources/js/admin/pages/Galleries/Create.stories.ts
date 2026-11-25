import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'

const project: App.Crm.Data.CaseSuggestionData = {
  id: 'p-rahn',
  name: 'Hochzeit Rahn',
  contactName: 'Jana Rahn',
  isBooked: true,
  galleryDefaults: { downloads: true, lists: true, comments: null },
}

const meta = {
  title: 'Bildschirme/Galerien/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/galerien/neu' },
  args: {
    mode: 'internal',
    project,
    shootId: 's-rahn',
    siblings: [{ id: 'g-rahn', title: 'Getting Ready', projectName: 'Hochzeit Rahn', projectType: 'wedding', projectTypeLabel: 'Hochzeit', assetCount: 48, version: 1, expiresAt: '2027-02-01', cover: null, status: 'published', downloadRule: 'open' }],
    address: 'galerie.example/hochzeit-rahn',
  },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Aus dem Shooting heraus angelegt: das Projekt steht schon. */
export const AusShooting: Story = {}

/** Ohne Projekt: nur der Titel. */
export const OhneProjekt: Story = { args: { project: null, shootId: null, siblings: [], address: 'galerie.example' } }

/** Eine Galerie, die anderswo liegt: nur Projekt, Titel und Adresse. */
export const Extern: Story = { args: { mode: 'external' } }
