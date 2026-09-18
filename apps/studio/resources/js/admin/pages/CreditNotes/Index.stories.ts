import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

/*
 * #409: die Gutschriften des ganzen Buchs. Eine Gutschrift entsteht an ihrer
 * Rechnung, gezählt wird sie am Jahresende — deshalb dieselbe Tabelle wie bei
 * den Rechnungen, nur ohne Status: eine Gutschrift ist ausgestellt oder es
 * gibt sie nicht.
 */
const page = {
  auth: {
    user: { id: 'u1', name: 'Christian Zepter', email: 'christian@atelier-nordlicht.de' },
    can: ['manage_contracts', 'manage_quotes'] as App.Enums.Capability[],
  },
}

const meta = {
  title: 'Bildschirme/Gutschriften',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/gutschriften', page },
  args: {
    creditNotes: [
      {
        id: 'gs-2',
        number: 'GS-2026-002',
        issuedOn: '2026-09-11',
        amount: 10_000,
        reason: 'Zweite Kamera nicht erbracht',
        invoiceId: 'in-14',
        invoiceNumber: 'RE-2026-014',
        projectId: 'p-rahn',
        projectName: 'Rahn, Marie & Jonas',
        contact: 'Marie Rahn',
        hasDocument: true,
      },
      {
        id: 'gs-1',
        number: 'GS-2026-001',
        issuedOn: '2026-08-30',
        amount: 25_000,
        reason: 'Nachlass',
        invoiceId: 'in-11',
        invoiceNumber: 'RE-2026-011',
        projectId: 'p-brandt',
        projectName: 'Brandt, Familie',
        contact: 'Lena Brandt',
        hasDocument: true,
      },
    ],
  },
} satisfies Meta<typeof Index>

export default meta

type Story = StoryObj<typeof meta>

export const Liste: Story = {}

/** Der Normalfall im ersten Jahr: es gab noch nichts zurückzugeben. */
export const Leer: Story = {
  args: { creditNotes: [] },
}
