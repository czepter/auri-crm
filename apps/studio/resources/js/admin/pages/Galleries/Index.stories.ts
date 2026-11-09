import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const galleries: App.Gallery.Data.GalleryRowData[] = [
  { id: 'g-rahn', title: 'Rahn — Auswahl', projectName: 'Hochzeit Rahn', projectType: 'wedding', projectTypeLabel: 'Hochzeit', assetCount: 412, version: 3, expiresAt: '2026-11-30', cover: null, status: 'published', downloadRule: 'open' },
  { id: 'g-rahn-trauung', title: 'Rahn — Trauung', projectName: 'Hochzeit Rahn', projectType: 'wedding', projectTypeLabel: 'Hochzeit', assetCount: 96, version: 1, expiresAt: '2026-11-30', cover: null, status: 'prerelease', downloadRule: 'open' },
  { id: 'g-brandt', title: 'Brandt — Vorauswahl', projectName: 'Familie Brandt — Hochzeit', projectType: 'portrait', projectTypeLabel: 'Portrait', assetCount: 38, version: null, expiresAt: null, cover: null, status: 'draft', downloadRule: 'off' },
]

const settings: App.Settings.Data.GallerySettingsData = {
  id: 'gs-1',
  expiryDays: 90,
  downloadRule: 'open',
  listsEnabled: true,
  commentsEnabled: false,
  design: { cover: 'zentriert', type: 'serif', colour: 'hell', size: 'mittel', spacing: 'eng', flow: 'zeilen' },
}

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
    sets: [
      { name: 'Highlights', access: 'internal' },
      { name: 'Getting Ready', access: 'internal' },
      { name: 'Fotobox', access: 'guests' },
    ],
    viewMode: 'text',
    downloadRule: 'after_payment',
    downloadSizes: ['original', 'web'],
    permissions: {
      client: { favorites: true, comments: true, hide: true, share: true },
      guest: { favorites: true, comments: false, hide: false, share: true },
    },
    introEnabled: true,
    expiryReminder: true,
    mailTemplateFamilyId: 'mt-galerie',
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

const meta = {
  title: 'Bildschirme/Galerien/Liste',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/galerien' },
  args: { galleries, settings, presets, mailTemplates: [{ id: 'mt-galerie', name: 'Galerie online' }], galleryHost: 'bilder.atelier-nordlicht.de' },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Der Normalfall: drei Galerien, eine davon noch unveröffentlicht. */
export const MitGalerien: Story = {}

/** Die Einstellungen offen auf den Vorlagen: Aufbau, Gestaltung, Freigabe, Ablauf, Versand. */
export const Vorlagen: Story = { parameters: { url: '/galerien?einstellungen=vorlagen' } }

/** Vor der ersten Galerie steht nur die Kopfzeile mit ihren Schaltflächen. */
export const Leer: Story = { args: { galleries: [] } }
