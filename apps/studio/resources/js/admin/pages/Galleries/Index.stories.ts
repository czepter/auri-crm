import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const galleries: App.Gallery.Data.GalleryRowData[] = [
  { id: 'g-rahn', title: 'Rahn — Auswahl', projectName: 'Hochzeit Rahn', assetCount: 412, version: 3, expiresAt: '2026-11-30' },
  { id: 'g-rahn-trauung', title: 'Rahn — Trauung', projectName: 'Hochzeit Rahn', assetCount: 96, version: 1, expiresAt: '2026-11-30' },
  { id: 'g-brandt', title: 'Brandt — Vorauswahl', projectName: 'Familie Brandt — Hochzeit', assetCount: 38, version: null, expiresAt: null },
]

const settings: App.Settings.Data.GallerySettingsData = {
  id: 'gs-1',
  expiryDays: 90,
  requiresSettledBalance: true,
  downloadsEnabled: true,
  listsEnabled: true,
  commentsEnabled: false,
  design: { cover: 'full', type: 'serif', colour: 'light', grid: 'wide' },
}

const presets: App.Gallery.Data.GalleryPresetData[] = [
  {
    id: 'gp-hochzeit',
    name: 'Hochzeit',
    design: { cover: 'full', type: 'serif', colour: 'light', grid: 'wide' },
    expiryMode: 'days',
    expiryDays: 90,
    requiresSettledBalance: true,
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
    requiresSettledBalance: false,
    downloadsEnabled: true,
    listsEnabled: false,
    commentsEnabled: false,
    isDefault: false,
  },
]

const meta = {
  title: 'Bildschirme/Galerien/Liste',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/galerien' },
  args: { galleries, settings, presets, galleryHost: 'bilder.atelier-nordlicht.de' },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Der Normalfall: drei Galerien, eine davon noch unveröffentlicht. */
export const MitGalerien: Story = {}

/** Vor der ersten Galerie steht nur die Kopfzeile mit ihren Schaltflächen. */
export const Leer: Story = { args: { galleries: [] } }
