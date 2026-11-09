import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'

const presets: App.Gallery.Data.GalleryPresetData[] = [
  {
    id: 'gp-hochzeit',
    name: 'Hochzeit',
    design: { cover: 'zentriert', type: 'serif', colour: 'hell', size: 'mittel', spacing: 'eng', flow: 'zeilen' },
    expiryMode: 'days',
    expiryDays: 90,
    downloadsEnabled: true,
    listsEnabled: true,
    commentsEnabled: false,
    isDefault: true,
    sets: null,
    viewMode: null,
    downloadRule: null,
    downloadSizes: null,
    permissions: null,
    introEnabled: null,
    expiryReminder: null,
    mailTemplateFamilyId: null,
  },
  {
    id: 'gp-portraet',
    name: 'Porträt',
    design: { cover: 'links', type: 'sans', colour: 'dunkel', size: 'klein', spacing: 'weit', flow: 'spalten' },
    expiryMode: 'none',
    expiryDays: null,
    downloadsEnabled: true,
    listsEnabled: false,
    commentsEnabled: false,
    isDefault: false,
    sets: null,
    viewMode: null,
    downloadRule: null,
    downloadSizes: null,
    permissions: null,
    introEnabled: null,
    expiryReminder: null,
    mailTemplateFamilyId: null,
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
    siblings: [{ id: 'g-rahn', title: 'Getting Ready', projectName: 'Hochzeit Rahn', projectType: 'wedding', projectTypeLabel: 'Hochzeit', assetCount: 48, version: 1, expiresAt: '2027-02-01', cover: null, status: 'published', downloadRule: 'open' }],
    address: 'galerie.example/hochzeit-rahn',
    presets,
    studioDefaults: {
      downloadRule: 'open',
      lists: true,
      comments: false,
      expiryDays: 90,
      design: { cover: 'zentriert', type: 'serif', colour: 'hell', size: 'mittel', spacing: 'eng', flow: 'zeilen' },
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
