import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Record from './Record.vue'

const gallery = {
  id: 'g-rahn',
  title: 'Rahn — Auswahl',
  slug: 'rahn-auswahl',
  url: 'https://bilder.atelier-nordlicht.de/g/rahn-auswahl',
  eventDate: '2026-09-13',
  viewPassword: 'basthorst',
  downloadPin: '4417',
  requiresVerifiedEmail: false,
  expiresAt: '2026-11-30',
  balanceSettled: true,
  requiresSettledBalance: true,
  design: { cover: 'full', type: 'serif', colour: 'light', grid: 'wide' },
  offers: { downloads: true, lists: true, comments: false },
  deviates: false,
  withdrawn: false,
  prerelease: false,
  isExternal: false,
  canPublish: true,
  canMail: true,
}

const sets = [
  { id: 's-trauung', name: 'Trauung', count: 168, sortKey: 'taken_at', sortDir: 'asc', density: 'medium' },
  { id: 's-feier', name: 'Feier', count: 244, sortKey: 'taken_at', sortDir: 'asc', density: 'medium' },
]

const assets = ['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7'].map((id, index) => ({
  id,
  key: `rahn/${id}`,
  setId: 's-trauung',
  src: `/galerien/g-rahn/bilder/${id}/vorschau`,
  filename: `rahn-${id}.jpg`,
  width: 1600,
  height: 1067,
  isCover: index === 0,
}))

const nav: App.Gallery.Data.GalleryNavData = {
  cover: '/galerien/g-rahn/bilder/a1/vorschau',
  tabs: [
    {
      key: 'bilder',
      label: 'galleryRecord.tab.bilder',
      href: '/galerien/g-rahn/bilder',
      count: 412,
      countTo: null,
      children: sets.map((set) => ({
        key: set.id,
        label: set.name,
        translate: false,
        href: `/galerien/g-rahn/bilder/${set.id}`,
        count: set.count,
        reorderable: true,
      })),
    },
    { key: 'gestaltung', label: 'galleryRecord.tab.gestaltung', href: '/galerien/g-rahn/gestaltung', count: null, countTo: null, children: [] },
    { key: 'einstellungen', label: 'galleryRecord.tab.einstellungen', href: '/galerien/g-rahn/einstellungen/allgemein', count: null, countTo: null, children: [] },
    { key: 'aktivitaet', label: 'galleryRecord.tab.aktivitaet', href: '/galerien/g-rahn/aktivitaet', count: 96, countTo: null, children: [] },
  ],
}

const meta = {
  title: 'Bildschirme/Galerien/Datensatz',
  component: Record,
  parameters: { layout: 'fullscreen', url: '/galerien/g-rahn/bilder' },
  args: {
    gallery,
    siblings: [{ id: 'g-rahn-trauung', title: 'Rahn — Trauung', assetCount: 96, open: false }],
    presets: [],
    figures: { published: 3, assets: 412, expiresAt: '2026-11-30', marked: 64, downloaded: 21, viewed: 96 },
    sets,
    assets,
    versions: [
      { version: 3, publishedAt: '2026-09-28', assetCount: 412 },
      { version: 2, publishedAt: '2026-09-21', assetCount: 380 },
      { version: 1, publishedAt: '2026-09-19', assetCount: 168 },
    ],
    builds: [{ id: 'b-1', size: 'web', status: 'ready', parts: 1, bytes: 1_840_000_000 }],
    lists: [{ id: 'l-1', name: 'Eltern Rahn', owner: 'jana.rahn@posteo.de', count: 42 }],
    activity: {
      downloads: [{ who: 'jana.rahn@posteo.de', what: 'Trauung', size: 'web', on: '2026-10-02' }],
      marks: [{ who: 'jana.rahn@posteo.de', what: 'rahn-a3.jpg', on: '2026-10-01' }],
      comments: [],
      views: [{ who: 'michael.rahn@posteo.de', on: '2026-09-30' }],
    },
    failedAssets: [],
    pendingUploads: [],
    nav,
    area: 'bilder',
    activeSetId: 's-trauung',
    settingsSection: 'allgemein',
  },
} satisfies Meta<typeof Record>

export default meta
type Story = StoryObj<typeof meta>

/** Der Bereich Bilder mit dem Set Trauung offen. */
export const Bilder: Story = {}

/** Ein leerer Entwurf: ein Set, keine Bilder, noch nichts veröffentlicht. */
export const BilderLeer: Story = {
  name: 'Bilder ohne Bilder',
  args: {
    gallery: { ...gallery, design: null, deviates: true },
    assets: [],
    sets: [{ id: 's-trauung', name: 'Trauung', count: 0, sortKey: 'taken_at', sortDir: 'asc', density: 'medium' }],
    versions: [],
    builds: [],
    figures: { published: null, assets: 0, expiresAt: null, marked: 0, downloaded: 0, viewed: 0 },
  },
}

/** Der Bereich Gestaltung: links die Wahl, rechts das Titelbild darüber. */
export const Gestaltung: Story = {
  args: { area: 'gestaltung' },
  parameters: { url: '/galerien/g-rahn/gestaltung' },
}

/** Der Bereich Einstellungen, Abschnitt Allgemein. */
export const EinstellungenAllgemein: Story = {
  args: { area: 'einstellungen' },
  parameters: { url: '/galerien/g-rahn/einstellungen/allgemein' },
}

/** Derselbe Bereich beim Zugang: Kennwort, PIN und Frist. */
export const EinstellungenZugang: Story = {
  args: { area: 'einstellungen', settingsSection: 'zugang' },
  parameters: { url: '/galerien/g-rahn/einstellungen/zugang' },
}

/** Der Bereich Aktivität mit den drei Blättern darunter. */
export const Aktivitaet: Story = {
  args: { area: 'aktivitaet' },
  parameters: { url: '/galerien/g-rahn/aktivitaet' },
}
