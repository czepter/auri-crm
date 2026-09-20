import type { Meta, StoryObj } from '@storybook/vue3-vite'
import type { PaletteEntry } from '@/admin/documents/blocks/types'
import Edit from './Edit.vue'

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

const MERGE_FIELDS = [
  { path: 'kunde.name', sample: 'Lena Brandt' },
  { path: 'auftrag.name', sample: 'Hochzeit Brandt · Gut Basthorst' },
  { path: 'shooting.datum', sample: '11.06.2027' },
  { path: 'angebot.summe', sample: '4.260,00 €' },
]

const meta = {
  title: 'Bildschirme/Dokumentvorlagen/Bearbeiten',
  component: Edit,
  parameters: { layout: 'fullscreen', url: '/dokumentvorlagen/dt1' },
  args: {
    palette: ANGEBOT,
    mergeFields: MERGE_FIELDS,
    template: {
      id: 'dt1',
      name: 'Angebot Hochzeit',
      body: '',
      blocks: {
        blocks: [
          { type: 'header', data: { text: 'Angebot', level: 1 } },
          { type: 'paragraph', data: { text: 'Für {{ kunde.name }}: {{ auftrag.name }} am {{ shooting.datum }}.' } },
        ],
      },
      version: 4,
      type: 'quote',
      typeLabel: 'Angebot',
      shootKind: 'Hochzeitstag',
    },
  },
} satisfies Meta<typeof Edit>

export default meta
type Story = StoryObj<typeof meta>

/** Eine Vorlage in Version 4, mit Blöcken aus dem Editor. */
export const Angebot: Story = {}

/** Vor dem Block-Editor geschrieben: blocks ist null, der Rumpf wird als ein roher Block importiert. */
export const AusAltemHtml: Story = {
  args: {
    template: {
      id: 'dt3',
      name: 'Angebot Porträt',
      body: '<h1>Angebot</h1><p>Für {{ kunde.name }}.</p>',
      blocks: null,
      version: 1,
      type: 'quote',
      typeLabel: 'Angebot',
      shootKind: null,
    },
  },
}
