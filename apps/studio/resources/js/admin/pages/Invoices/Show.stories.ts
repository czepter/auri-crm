import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Show from './Show.vue'

const meta = {
  title: 'Bildschirme/Rechnungen/Rechnung',
  component: Show,
  parameters: { layout: 'fullscreen', url: '/rechnungen/in-5' },
  args: {
    hasCreditNoteDraft: false,
    invoice: {
      row: {
        id: 'in-5',
        number: 'RE-2026-5',
        issuedOn: '2026-11-16',
        projectId: 'p-fuhrmann',
        projectName: 'Hochzeit Marie & Adrian',
        contact: 'Marie Fuhrmann',
        amount: 29_500,
        paidAmount: 0,
        credited: 0,
        dueOn: '2026-11-22',
        status: 'open',
        statusLabel: 'Offen',
        overdueDays: null,
        sentOn: null,
        viewedOn: null,
        views: [],
        hasDocument: true,
        openInstallments: [{ id: 'ra-1', remaining: 29_500 }],
        creditNotes: [],
      },
      recipient: 'Marie Fuhrmann',
      quoteId: 'q-3',
      quoteNumber: 'AN-2026-3',
      items: [{ title: 'Reservierungsgebühr', description: null, quantity: 1, unitPrice: 29_500, total: 29_500, gross: 29_500 }],
      net: 29_500,
      vat: 0,
      gross: 29_500,
      vatRate: '0 %',
      events: [{ kind: 'created', on: '2026-11-16', amount: null }],
    },
  },
} satisfies Meta<typeof Show>

export default meta
type Story = StoryObj<typeof meta>

export const Offen: Story = {}

export const GutschriftEntwurf: Story = {
  args: {
    hasCreditNoteDraft: true,
    invoice: { ...meta.args.invoice, row: { ...meta.args.invoice.row, status: 'paid', statusLabel: 'Bezahlt', paidAmount: 29_500, openInstallments: [] } },
  },
}
