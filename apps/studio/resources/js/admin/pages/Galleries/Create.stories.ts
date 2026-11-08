import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'

const presets: App.Gallery.Data.GalleryPresetData[] = [
  {
    id: 'gp-hochzeit',
    name: 'Hochzeit',
    design: { cover: 'full', type: 'serif', colour: 'light', grid: 'wide' },
    expiryMode: 'days',
    expiryDays: 90,
    downloadsEnabled: true,
    listsEnabled: true,
    commentsEnabled: false,
    isDefault: true,
  },
  {
    id: 'gp-portraet',
    name: 'Porträt',
    design: { cover: 'plain', type: 'grotesk', colour: 'dark', grid: 'dense' },
    expiryMode: 'none',
    expiryDays: null,
    downloadsEnabled: true,
    listsEnabled: false,
    commentsEnabled: false,
    isDefault: false,
  },
]

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
    siblings: [{ id: 'g-rahn', title: 'Getting Ready', projectName: 'Hochzeit Rahn', assetCount: 48, version: 1, expiresAt: '2027-02-01', cover: null }],
    address: 'galerie.example/hochzeit-rahn',
    presets,
    studioDefaults: {
      downloads: true,
      lists: true,
      comments: false,
      expiryDays: 90,
      design: { cover: 'full', type: 'serif', colour: 'light', grid: 'wide' },
    },
  },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Aus dem Shooting heraus angelegt: Projekt und Vorlage stehen schon. */
export const AusShooting: Story = {}

/** Ohne Projekt und ohne Vorlagen bleiben nur die Studio-Standardwerte. */
export const OhneProjekt: Story = { args: { project: null, shootId: null, siblings: [], address: 'galerie.example', presets: [] } }

/** Eine Galerie, die anderswo liegt: nur Projekt, Titel und Adresse. */
export const Extern: Story = { args: { mode: 'external' } }
