import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'
import { catalogItems, catalogPackages, contacts, quoteItem, quoteOf, studioSettings } from '../__stories__/fixtures'

const brandt: App.Crm.Data.CaseSuggestionData = {
  id: 'p-brandt',
  name: 'Brandt, Familie',
  contactName: 'Katrin Brandt',
  isBooked: false,
  galleryDefaults: { downloads: null, lists: null, comments: null },
}

const draft = {
  status: 'draft',
  statusLabel: 'Entwurf',
  sentAt: null,
  validUntil: '2026-10-26',
  recipientContact: 'c-brandt',
  recipientStreet: 'Am Burgfeld 4',
  recipientPostcode: '23568',
  recipientCity: 'Lübeck',
  intro: 'Liebe Katrin, lieber Sven, danke für eure Anfrage zur Trauung am 12.06.2027.',
  paymentStages: [
    { id: 'ps-1', percent: 25, label: 'Bei der Zusage', minimum: 80000, note: null, dueOn: null },
    { id: 'ps-2', percent: 50, label: 'Am Termin', minimum: 0, note: null, dueOn: null },
    { id: 'ps-3', percent: 25, label: 'Bei der Galerie', minimum: 0, note: null, dueOn: null },
  ],
}

const auswahl = quoteOf({
  ...draft,
  id: 'q-brandt-auswahl',
  number: null,
  pricingMode: 'choice',
  net: 215000,
  hourlyRate: 25000,
  minHours: 3,
  maxHours: 12,
  hours: 6,
  imagesPerHour: 40,
  timeTitle: 'Reportage',
  timeDescription: 'Trauung, Empfang, Paarshooting',
  timeSortOrder: 0,
  items: [
    quoteItem({ id: 'qi-1', title: 'Galerie mit Download', lineTotal: 0, isIncluded: true }),
    quoteItem({ id: 'qi-2', title: 'Content Creator', lineTotal: 40000, selectable: true, selected: true, outcome: 'Reels und TikToks, hochkant' }),
    quoteItem({ id: 'qi-3', title: 'Analog, 35 mm', lineTotal: 25000, selectable: true, selected: true, outcome: 'Zwei bis drei Rollen Kodak Gold' }),
    quoteItem({ id: 'qi-4', title: 'Raw Dump', lineTotal: 15000, selectable: true, selected: false, outcome: 'Alle Dateien, nur farbkorrigiert' }),
    quoteItem({ id: 'qi-5', title: 'Fine-Art-Album', lineTotal: 69000, selectable: true, selected: false, outcome: 'Vierzig Seiten, 30 × 30' }),
  ],
})

const klassisch = quoteOf({
  ...draft,
  id: 'q-brandt-klassisch',
  number: null,
  pricingMode: 'classic',
  net: 309000,
  items: [
    quoteItem({ id: 'qi-6', title: 'Hochzeitsreportage ganztags', lineTotal: 240000 }),
    quoteItem({ id: 'qi-7', title: 'Galerie mit Download', lineTotal: 0, isIncluded: true }),
    quoteItem({ id: 'qi-8', title: 'Fine-Art-Album', lineTotal: 69000 }),
    quoteItem({ id: 'qi-9', title: 'Fahrt in Norddeutschland', lineTotal: 0, isIncluded: true }),
  ],
})

const meta = {
  title: 'Bildschirme/Angebote/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/angebote/neu' },
  args: {
    project: brandt,
    quotes: [auswahl],
    packages: catalogPackages,
    catalogItems,
    contacts,
    canManageCatalog: true,
    vatSettings: { mode: 'standard', rate: 1900, note: null },
    studioSettings,
    missingBusinessData: [],
  },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Aus der Übersicht geöffnet, noch ohne Projekt oder Anfrage. */
export const Auswahl: Story = { args: { project: null, quotes: [] } }

/** Der Entwurf im Modus Auswahl: Zeit mit Spanne, Positionen mit Portal-Status. */
export const ModusAuswahl: Story = {}

/** Derselbe Entwurf klassisch: keine Zeit-Karte, feste Positionen. */
export const ModusKlassisch: Story = { args: { quotes: [klassisch] } }

/** Das erste Angebot eines Studios, dem die Impressumsdaten noch fehlen. */
export const OhneGeschaeftsdaten: Story = {
  args: { missingBusinessData: ['legal_name', 'street', 'postcode', 'city'] },
}
