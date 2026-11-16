import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'
import { catalogItems, catalogPackages, studioSettings } from '../__stories__/fixtures'

const fuhrmann: App.Crm.Data.CaseSuggestionData = {
  id: 'p-fuhrmann',
  name: 'Hochzeit Marie & Adrian',
  contactName: 'Marie Fuhrmann',
  isBooked: true,
  galleryDefaults: { downloads: null, lists: null, comments: null },
}

const others: App.Crm.Data.CaseSuggestionData[] = [
  { ...fuhrmann, id: 'p-lisa', name: 'Hochzeit Lisa + Lennart', contactName: 'Elisabeth Zepter' },
  { ...fuhrmann, id: 'p-viola', name: 'Hochzeit Viola Marschner & Chris', contactName: 'Viola Marschner' },
]

const letterEditor = {
  palette: [
    { type: 'paragraph', group: 'text', label: 'Absatz', inline: false },
    { type: 'header', group: 'text', label: 'Überschrift', inline: false },
    { type: 'list', group: 'text', label: 'Liste', inline: false },
  ],
  mergeFields: [
    { path: 'contact.name', sample: 'Marie Fuhrmann' },
    { path: 'contact.street', sample: 'Gartenweg 4' },
    { path: 'contact.postcode', sample: '22765' },
    { path: 'contact.city', sample: 'Hamburg' },
    { path: 'project.name', sample: 'Hochzeit Marie & Adrian' },
    { path: 'invoice.number', sample: 'RE-2026-6' },
    { path: 'invoice.dueOn', sample: '30.11.2026' },
  ],
}

// Der Projekt-Suche antwortet im Katalog niemand; die Liste kommt aus der Story.
// Die Vorschau rendert nur der Server, hier bleibt sie leer.
const fetchRecent = () => {
  const original = window.fetch
  window.fetch = async (input, init) => String(input).includes('/suche')
    ? new Response(JSON.stringify({ projects: [fuhrmann, ...others], contacts: [] }))
    : original(input, init)

  return () => { window.fetch = original }
}

const meta = {
  title: 'Bildschirme/Rechnungen/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/rechnungen/neu', page: { letterEditor } },
  beforeEach: fetchRecent,
  args: {
    rates: { 'p-fuhrmann': [{ id: 'ra-1', label: 'Restzahlung', amount: 29_500, dueOn: '2026-11-22' }] },
    quoteLines: {},
    billable: [fuhrmann],
    project: null,
    packages: catalogPackages,
    catalogItems,
    vatSettings: { mode: 'small_business', rate: 0, note: 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.' },
    intro: '<p>{{ contact.name }}<br>{{ contact.street }}<br>{{ contact.postcode }} {{ contact.city }}</p><p>Leistung</p><p>Zahlung</p>',
    studioSettings: { ...studioSettings, displayName: '50mm freunde' },
    missingInvoiceData: [],
    firstInvoice: false,
  },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Aus der Übersicht geöffnet: noch kein Empfänger. */
export const Leer: Story = {}

/** Aus dem Projekt geöffnet, die offene Rate steht zum Übernehmen bereit. */
export const MitProjekt: Story = { args: { project: fuhrmann } }
