import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const AUFGABEN: App.Crm.Data.TaskTemplateData[] = [
  { id: 'tt1', title: 'Ablaufplan mit den Kunden durchgehen', audience: 'studio', audienceLabel: 'Studio', projectType: 'wedding', pipeline: 'shoot', stage: 'prepare', dayOffset: -14, anchor: 'shoot', anchorLabel: 'Shooting', shootKind: 'Hochzeitstag', isActive: true, sortOrder: 1 },
  { id: 'tt2', title: 'Auswahl an die Kunden schicken', audience: 'studio', audienceLabel: 'Studio', projectType: 'wedding', pipeline: 'shoot', stage: 'deliver', dayOffset: 21, anchor: 'shoot', anchorLabel: 'Shooting', shootKind: null, isActive: true, sortOrder: 2 },
  { id: 'tt3', title: 'Zweitfotograf bestätigen', audience: 'studio', audienceLabel: 'Studio', projectType: null, pipeline: 'shoot', stage: 'prepare', dayOffset: -30, anchor: 'shoot', anchorLabel: 'Shooting', shootKind: 'Hochzeitstag', isActive: false, sortOrder: 3 },
]

const FRAGEBOEGEN: App.Crm.Data.FormTemplateData[] = [
  { id: 'ft1', name: 'Vor der Trauung', projectType: 'wedding', projectTypeLabel: 'Hochzeit', isActive: true, fieldCount: 12, updatedAt: '2026-08-14T10:00:00+02:00', fields: [] },
]

const DOKUMENTE: App.Documents.Data.DocumentTemplateData[] = [
  { id: 'dt1', name: 'Fotovertrag Hochzeit', body: '', blocks: null, version: 4, type: 'contract', typeLabel: 'Vertrag', shootKind: 'Hochzeitstag' },
  { id: 'dt2', name: 'Angebotsanschreiben', body: '', blocks: null, version: 2, type: 'mail', typeLabel: 'E-Mail', shootKind: null },
]

const GALERIEN: App.Gallery.Data.GalleryPresetData[] = [
  { id: 'gp1', name: 'Hochzeit, sechs Monate', design: null, expiryMode: 'days', expiryDays: 180, requiresSettledBalance: true, downloadsEnabled: true, listsEnabled: true, commentsEnabled: false, isDefault: true },
]

const AUSWAHL = {
  audiences: [
    { value: 'studio', label: 'Studio' },
    { value: 'client', label: 'Kunden' },
  ],
  shootKinds: ['Hochzeitstag', 'Portrait'],
  projectTypes: [
    { value: 'wedding', label: 'Hochzeit' },
    { value: 'business', label: 'Business' },
  ],
}

const meta = {
  title: 'Bildschirme/Vorlagen/Übersicht',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/vorlagen' },
  args: {
    kind: null,
    kinds: ['aufgaben', 'fragebogen', 'dokumente', 'galerien'],
    counts: { aufgaben: 3, fragebogen: 1, dokumente: 2, galerien: 1 },
    taskTemplates: AUFGABEN,
    formTemplates: FRAGEBOEGEN,
    documentTemplates: DOKUMENTE,
    documentUse: { dt1: 7, dt2: 0 },
    galleryPresets: GALERIEN,
    ...AUSWAHL,
    selected: null,
    previewDate: null,
    previewShootKind: null,
    preview: null,
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Alle vier Arten nebeneinander, jede als eigene Karte. */
export const AlleArten: Story = {}

/** Eine Art gewählt: links die Liste, rechts der Editor der markierten Aufgabenvorlage mit ihrer Vorschau. */
export const Aufgaben: Story = {
  parameters: { url: '/vorlagen?typ=aufgaben' },
  args: {
    kind: 'aufgaben',
    selected: 'tt1',
    previewDate: '2027-06-11',
    previewShootKind: 'Hochzeitstag',
    preview: [
      { title: 'Zweitfotograf bestätigen', dueOn: '2027-05-12' },
      { title: 'Ablaufplan mit den Kunden durchgehen', dueOn: '2027-05-28' },
      { title: 'Auswahl an die Kunden schicken', dueOn: '2027-07-02' },
    ],
  },
}

/** Ein frisch eingerichtetes Studio: die Chips stehen auf null, jede Karte meldet sich leer. */
export const Leer: Story = {
  args: {
    counts: { aufgaben: 0, fragebogen: 0, dokumente: 0, galerien: 0 },
    taskTemplates: [],
    formTemplates: [],
    documentTemplates: [],
    documentUse: {},
    galleryPresets: [],
  },
}
