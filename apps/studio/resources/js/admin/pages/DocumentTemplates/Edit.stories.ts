import type { Meta, StoryObj } from '@storybook/vue3-vite'
import type { PaletteEntry } from '@/admin/documents/blocks/types'
import Edit from './Edit.vue'

const VERTRAG: PaletteEntry[] = [
  { type: 'paragraph', group: 'text', label: 'Absatz', inline: false },
  { type: 'header', group: 'text', label: 'Überschrift', inline: false },
  { type: 'list', group: 'text', label: 'Liste', inline: false },
  { type: 'table', group: 'layout', label: 'Tabelle', inline: false },
  { type: 'pagebreak', group: 'layout', label: 'Seitenumbruch', inline: false },
  { type: 'clause', group: 'contract', label: 'Klausel', inline: false },
  { type: 'signature', group: 'contract', label: 'Unterschriften', inline: false },
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
    palette: VERTRAG,
    mergeFields: MERGE_FIELDS,
    template: {
      id: 'dt1',
      name: 'Fotovertrag Hochzeit',
      body: '',
      blocks: {
        blocks: [
          { type: 'header', data: { text: 'Fotovertrag', level: 1 } },
          { type: 'paragraph', data: { text: 'Zwischen {{ kunde.name }} und Atelier Nordlicht für {{ auftrag.name }} am {{ shooting.datum }}.' } },
          { type: 'clause', data: { rows: [{ title: 'Leistungsumfang', body: 'Begleitung, 10 Stunden, inklusive Zweitfotograf ab dem Empfang.' }] } },
          { type: 'signature', data: { rows: [{ label: 'Kunden' }, { label: 'Atelier Nordlicht' }] } },
        ],
      },
      version: 4,
      type: 'contract',
      typeLabel: 'Vertrag',
      shootKind: 'wedding',
      shootKindLabel: 'Trauung',
    },
  },
} satisfies Meta<typeof Edit>

export default meta
type Story = StoryObj<typeof meta>

/** Eine Vorlage in Version 4, mit Blöcken aus dem Editor. */
export const Vertrag: Story = {}

/** Vor dem Block-Editor geschrieben: blocks ist null, der Rumpf wird als ein roher Block importiert. */
export const AusAltemHtml: Story = {
  args: {
    template: {
      id: 'dt3',
      name: 'Fotovertrag Porträt',
      body: '<h1>Fotovertrag</h1><p>Zwischen {{ kunde.name }} und Atelier Nordlicht.</p>',
      blocks: null,
      version: 1,
      type: 'contract',
      typeLabel: 'Vertrag',
      shootKind: null,
      shootKindLabel: null,
    },
  },
}
