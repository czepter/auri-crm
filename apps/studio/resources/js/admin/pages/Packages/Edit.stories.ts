import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Edit from './Edit.vue'

const meta = {
  title: 'Bildschirme/Pakete/Bearbeiten',
  component: Edit,
  parameters: { layout: 'fullscreen', url: '/pakete/pk1' },
  args: {
    package: {
      id: 'pk1',
      name: 'Ganztagsbegleitung',
      description: 'Getting Ready bis Eröffnungstanz',
      isActive: true,
      itemCount: 3,
      items: [
        { id: 'i1', title: 'Begleitung, 10 Stunden', description: null, quantity: 1, unitPrice: 298000, selectable: false, selected: true, outcome: null, hasImage: false },
        { id: 'i2', title: 'Zweitfotograf', description: 'Ab dem Empfang', quantity: 1, unitPrice: 60000, selectable: true, selected: true, outcome: null, hasImage: false },
        { id: 'i3', title: 'Album, 30 x 30, 40 Seiten', description: null, quantity: 1, unitPrice: 68000, selectable: true, selected: true, outcome: 'Gedrucktes Album', hasImage: true },
      ],
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
    package: { id: 'pk3', name: 'Trauung und Feier', description: null, isActive: false, itemCount: 0, items: [] },
  },
}
