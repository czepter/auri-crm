import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'

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

const project: App.Crm.Data.ProjectSuggestionData = {
  id: 'p-rahn',
  name: 'Hochzeit Rahn',
  contactName: 'Jana Rahn',
  galleryDefaults: { downloads: true, lists: true, comments: null },
}

const meta = {
  title: 'Bildschirme/Galerien/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/galerien/neu' },
  args: {
    project,
    shootId: 's-rahn',
    presets,
    studioDefaults: {
      downloads: true,
      lists: true,
      comments: false,
      expiryDays: 90,
      requiresSettledBalance: true,
      design: { cover: 'full', type: 'serif', colour: 'light', grid: 'wide' },
    },
  },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Aus dem Shooting heraus angelegt: Projekt und Vorlage stehen schon. */
export const AusShooting: Story = {}

/** Ohne Projekt und ohne Vorlagen bleiben nur die Studio-Standardwerte. */
export const OhneProjekt: Story = { args: { project: null, shootId: null, presets: [] } }
