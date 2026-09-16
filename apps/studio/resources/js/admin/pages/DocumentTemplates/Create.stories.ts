import type { Meta, StoryObj } from '@storybook/vue3-vite'
import type { PaletteEntry } from '@/admin/documents/blocks/types'
import Create from './Create.vue'

/* Der Ausschnitt aus DocumentBlockCatalog, den ein Angebot und eine E-Mail sehen. */
const ANGEBOT: PaletteEntry[] = [
  { type: 'paragraph', group: 'text', label: 'Absatz', inline: false },
  { type: 'header', group: 'text', label: 'Überschrift', inline: false },
  { type: 'list', group: 'text', label: 'Liste', inline: false },
  { type: 'table', group: 'layout', label: 'Tabelle', inline: false },
  { type: 'pagebreak', group: 'layout', label: 'Seitenumbruch', inline: false },
  { type: 'paymentPlan', group: 'data', label: 'Zahlungsplan', inline: false },
  { type: 'lineItems', group: 'data', label: 'Positionen', inline: false, allowed: false, only: 'Angebot, Rechnung' },
  { type: 'mergeField', group: 'data', label: 'Feld', inline: true },
]

const MAIL: PaletteEntry[] = [
  { type: 'paragraph', group: 'text', label: 'Absatz', inline: false },
  { type: 'header', group: 'text', label: 'Überschrift', inline: false },
  { type: 'list', group: 'text', label: 'Liste', inline: false },
  { type: 'callout', group: 'layout', label: 'Hinweis', inline: false },
  { type: 'pagebreak', group: 'layout', label: 'Seitenumbruch', inline: false, allowed: false, only: 'Angebot, Rechnung' },
  { type: 'mergeField', group: 'data', label: 'Feld', inline: true },
]

const MERGE_FIELDS = [
  { path: 'kunde.name', sample: 'Lena Brandt' },
  { path: 'kunde.adresse', sample: 'Eppendorfer Weg 214, 20251 Hamburg' },
  { path: 'auftrag.name', sample: 'Hochzeit Brandt · Gut Basthorst' },
  { path: 'shooting.datum', sample: '11.06.2027' },
  { path: 'angebot.summe', sample: '4.260,00 €' },
]

const ANGEBOT_STARTER = {
  key: 'wedding-quote',
  name: 'Angebot Hochzeit',
  description: 'Leistungen, Zahlungsplan, Nutzungsrechte, Ausfallstaffel.',
  shootKind: 'Hochzeitstag',
  blocks: {
    blocks: [
      { type: 'header', data: { text: 'Angebot', level: 1 } },
      { type: 'paragraph', data: { text: 'Für {{ kunde.name }}: {{ auftrag.name }} am {{ shooting.datum }}.' } },
      { type: 'paymentPlan', data: { rows: [{ label: 'Anzahlung', amount: '{{ angebot.summe }}' }] } },
    ],
  },
}

const meta = {
  title: 'Bildschirme/Dokumentvorlagen/Neu',
  component: Create,
  parameters: { layout: 'fullscreen', url: '/dokumentvorlagen/neu' },
  args: {
    types: [
      { value: 'quote', label: 'Angebot' },
      { value: 'invoice', label: 'Rechnung' },
      { value: 'mail', label: 'E-Mail' },
    ],
    shootKinds: ['Hochzeitstag', 'Portrait'],
    palettes: { quote: ANGEBOT, mail: MAIL },
    mergeFields: MERGE_FIELDS,
    starters: {
      quote: [
        ANGEBOT_STARTER,
        {
          key: 'portrait-quote',
          name: 'Angebot Porträt',
          description: 'Kurzfassung für Einzeltermine.',
          shootKind: 'Portrait',
          blocks: {
            blocks: [
              { type: 'header', data: { text: 'Angebot', level: 1 } },
              { type: 'paragraph', data: { text: '{{ kunde.name }}, Termin am {{ shooting.datum }}.' } },
            ],
          },
        },
      ],
      mail: [],
    },
    projects: [
      { id: 'p1', name: 'Hochzeit Brandt · Gut Basthorst' },
      { id: 'p2', name: 'Businessporträts Nordwerk' },
    ],
  },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Der Einstieg: zwei Vorlagen zur Wahl und die leere Karte daneben. */
export const Auswahl: Story = {}

/** Ein Typ ohne Vorlage — es bleibt die leere Karte. */
export const OhneVorlage: Story = { args: { starters: { quote: [], mail: [] } } }
