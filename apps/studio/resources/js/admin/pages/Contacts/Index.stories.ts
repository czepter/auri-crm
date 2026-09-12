import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const KONTAKTE: App.Crm.Data.ContactRowData[] = [
  {
    id: 'c1',
    name: 'Lena Brandt',
    email: 'lena.brandt@posteo.de',
    phone: '0176 2244881',
    projectCount: 2,
    standing: 'running',
    nextDate: '2027-06-11',
    latestProject: 'Hochzeit Brandt · Gut Basthorst',
  },
  {
    id: 'c2',
    name: 'Tom Brandt',
    email: 'tom@brandt-tischlerei.de',
    phone: null,
    projectCount: 2,
    standing: 'running',
    nextDate: '2027-06-11',
    latestProject: 'Hochzeit Brandt · Gut Basthorst',
  },
  {
    id: 'c3',
    name: 'Kerstin Voß',
    email: 'k.voss@nordwerk.de',
    phone: '040 559012',
    projectCount: 1,
    standing: 'past',
    nextDate: null,
    latestProject: 'Businessporträts Nordwerk',
  },
]

const meta = {
  title: 'Bildschirme/Kontakte/Übersicht',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/kontakte' },
  args: { contacts: KONTAKTE },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Drei Kontakte, nach Stand gruppiert: zwei laufende, einer abgeschlossen. */
export const Gruppiert: Story = {}

/** Ein Kontakt ohne E-Mail und ohne Telefon — die Spalte Erreichbar bleibt leer. */
export const OhneErreichbarkeit: Story = {
  args: {
    contacts: [
      { ...KONTAKTE[2], id: 'c4', name: 'Kerstin Voß', email: null, phone: null, standing: 'none', projectCount: 0, latestProject: null },
    ],
  },
}

/** Ein frisch eingerichtetes Studio: die Tabelle weicht dem Anlegen-Verweis. */
export const Leer: Story = { args: { contacts: [] } }
