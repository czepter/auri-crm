import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

/** Preise stehen in Cent — siehe PackageItem::$unit_price und admin/lib/money.ts. */
const GANZTAG: App.Catalog.Data.PackageData = {
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
}

const TRAUUNG: App.Catalog.Data.PackageData = {
  id: 'pk2',
  name: 'Trauung und Feier',
  description: null,
  isActive: false,
  itemCount: 3,
  items: [
    { id: 'i4', title: 'Begleitung, 6 Stunden', description: null, quantity: 1, unitPrice: 288000, vatRate: null, selectable: false, selected: true, outcome: null, hasImage: false },
    { id: 'i5', title: 'Album, 25 x 25, 30 Seiten', description: null, quantity: 1, unitPrice: 60000, vatRate: null, selectable: true, selected: false, outcome: 'Gedrucktes Album', hasImage: false },
    { id: 'i6', title: 'Online-Galerie', description: 'Sechs Monate abrufbar', quantity: 1, unitPrice: null, vatRate: null, selectable: false, selected: true, outcome: 'Galerie', hasImage: false },
  ],
  portalOffered: false,
  portalPriceMode: 'exact',
  portalRequiresDate: true,
  projectTypeKey: null,
  portalHasImage: false,
  price: 348000,
  requestedCount: 0,
  bookedCount: 0,
}

const meta = {
  title: 'Bildschirme/Pakete/Übersicht',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/pakete' },
  args: { packages: [GANZTAG, TRAUUNG] },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Beide Gruppen: eins im Portal, eins nur für Angebote und deaktiviert. */
export const Zwei: Story = {}

/** Ein frisch eingerichtetes Studio hat noch kein Paket. */
export const Leer: Story = { args: { packages: [] } }
