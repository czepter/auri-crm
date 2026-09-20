import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Edit from './Edit.vue'

const meta = {
  title: 'Bildschirme/Pakete/Bearbeiten',
  component: Edit,
  parameters: { layout: 'fullscreen', url: '/pakete/pk1' },
  args: {
    vatRate: 1900,
    catalogItems: [
      { id: 'ci-1', title: 'Editierte Datei extra', description: null, unitPrice: 2500 },
      { id: 'ci-2', title: 'Drohnenaufnahmen', description: null, unitPrice: 9000 },
      { id: 'ci-3', title: 'Express-Bearbeitung (48h)', description: null, unitPrice: 6000 },
    ],
    requests: [
      { id: 'ld-1', project: 'pr-1', contact: 'Viola Neumann & Chris Hagen', requestedOn: '2027-03-02', date: '2027-08-21', booked: false },
      { id: 'ld-2', project: 'pr-2', contact: 'Lea Meier & Jan Kowalski', requestedOn: '2027-02-14', date: '2027-06-06', booked: true },
    ],
    projectTypes: [
      { key: 'hochzeit', name: 'Hochzeit' },
      { key: 'portrait', name: 'Portrait' },
      { key: 'sonstige', name: 'Sonstige' },
    ],
    package: {
      id: 'pk1',
      name: 'Ganztagsbegleitung',
      description: 'Getting Ready bis Eröffnungstanz',
      isActive: true,
      itemCount: 3,
      items: [
        { id: 'i1', title: 'Begleitung, 10 Stunden', description: null, quantity: 1, unitPrice: 298000, vatRate: null, selectable: false, selected: true, outcome: null, hasImage: false },
        { id: 'i2', title: 'Zweitfotograf', description: 'Ab dem Empfang', quantity: 1, unitPrice: 60000, vatRate: null, selectable: true, selected: true, outcome: null, hasImage: false },
        { id: 'i3', title: 'Album, 30 x 30, 40 Seiten', description: null, quantity: 1, unitPrice: 68000, vatRate: null, selectable: true, selected: true, outcome: 'Gedrucktes Album', hasImage: true },
      ],
      portalOffered: true,
      portalPriceMode: 'from',
      portalRequiresDate: true,
      projectTypeKey: 'hochzeit',
      portalHasImage: false,
      price: 426000,
      requestedCount: 7,
      bookedCount: 4,
      showcaseCount: 0,
    },
  },
} satisfies Meta<typeof Edit>

export default meta
type Story = StoryObj<typeof meta>

/** Drei Positionen, zusammen 4.260 €, eine davon mit Bild. */
export const Vollstaendig: Story = {}

/** Ein Paket ohne Positionen: die Liste steht, gefüllt wird sie hier. */
export const OhnePositionen: Story = {
  args: {
    package: {
      id: 'pk3', name: 'Trauung und Feier', description: null, isActive: false, itemCount: 0, items: [],
      portalOffered: false, portalPriceMode: 'exact', portalRequiresDate: true,
      projectTypeKey: null, portalHasImage: false, price: 0, requestedCount: 0, bookedCount: 0, showcaseCount: 0,
    },
    requests: [],
  },
}
