import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Record from './Record.vue'

const gallery = {
  id: 'g-rahn',
  title: 'Rahn — Auswahl',
  slug: 'rahn-auswahl',
  url: 'https://bilder.atelier-nordlicht.de/rahn/auswahl',
  eventDate: '2026-09-13',
  viewPassword: 'basthorst',
  downloadPin: '4417',
  requiresVerifiedEmail: false,
  expiresAt: '2026-11-30',
  design: { cover: 'zentriert', type: 'serif', colour: 'hell', size: 'mittel', spacing: 'eng', flow: 'zeilen' },
  coverFocal: null,
  offers: { downloads: true, lists: true, comments: false },
  permissions: {
    client: { favorites: true, comments: true, hide: true, share: true },
    guest: { favorites: true, comments: false, hide: false, share: true },
  },
  introEnabled: false,
  studioName: 'Atelier Nordlicht',
  viewMode: 'none' as const,
  watermarkText: null,
  downloadRule: 'open' as const,
  downloadInvoiceId: null,
  downloadSizes: ['original', 'web'] as ('original' | 'web')[],
  expiryReminder: false,
  invoices: [{ id: 'inv1', number: 'R-2026-004', status: 'open' }],
  deviates: false,
  withdrawn: false,
  prerelease: false,
  isExternal: false,
  externalUrl: null,
  externalPassword: null,
  info: null,
  canPublish: true,
  canDelete: true,
  copy: null,
  canMail: true,
  recipient: { name: 'Lena Brandt', email: 'lena@example.test' },
}

const sets = [
  { id: 's-trauung', name: 'Trauung', count: 168, sortKey: 'taken_at', sortDir: 'asc', density: 'medium', access: 'guests' as const, url: 'https://example.test/bahlmann/hochzeit-bahlmann?set=s-trauung' },
  { id: 's-feier', name: 'Feier', count: 244, sortKey: 'taken_at', sortDir: 'asc', density: 'medium', access: 'internal' as const, url: 'https://example.test/bahlmann/hochzeit-bahlmann?set=s-feier' },
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
        tag: set.access === 'guests' ? 'galleryRecord.guestsTag' : null,
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
      counts: { favorites: 3, comments: 1, hidden: 1, downloads: 1 },
      events: [
        { type: 'comment', at: '2026-10-02T10:12:00+02:00', id: 'c1', studio: false, who: 'Jana Rahn', photo: { n: 12, src: '' }, text: 'Das hier bitte als Leinwand.' },
        { type: 'views', at: '2026-10-02T00:00:00+02:00', count: 4 },
        { type: 'favorites', at: '2026-10-01T18:40:00+02:00', who: 'Jana Rahn', photos: [{ n: 3, src: '' }, { n: 12, src: '' }, { n: 27, src: '' }] },
        { type: 'hide', at: '2026-10-01T18:50:00+02:00', id: 'h-1', who: 'Michael Rahn', photo: { n: 8, src: '' } },
        { type: 'download', at: '2026-09-30T00:00:00+02:00', what: null, size: 'web' },
      ],
      favorites: [{ n: 3, src: '' }, { n: 12, src: '' }, { n: 27, src: '' }],
      hidden: [{ id: 'h-1', who: 'Michael Rahn', photo: { n: 8, src: '' } }],
      task: { available: true, url: null },
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
    sets: [{ id: 's-trauung', name: 'Trauung', count: 0, sortKey: 'taken_at', sortDir: 'asc', density: 'medium', access: 'internal' as const, url: 'https://example.test/bahlmann/hochzeit-bahlmann?set=s-trauung' }],
    versions: [],
    builds: [],
    figures: { published: null, assets: 0, expiresAt: null, marked: 0, downloaded: 0, viewed: 0 },
  },
}

/** Eine Kopie, deren Bilder noch eingereiht sind. */
export const Kopie: Story = {
  args: { gallery: { ...gallery, copy: { done: 12, total: 40 } } },
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

/** Der Bereich Aktivität: Verlauf links, Favoriten und Ausgeblendetes rechts. */
export const Aktivitaet: Story = {
  args: { area: 'aktivitaet' },
  parameters: { url: '/galerien/g-rahn/aktivitaet' },
}
