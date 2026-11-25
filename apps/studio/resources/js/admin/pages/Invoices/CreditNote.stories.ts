import type { Meta, StoryObj } from '@storybook/vue3-vite'
import CreditNote from './CreditNote.vue'

const letterEditor = {
  palette: [
    { type: 'paragraph', group: 'text', label: 'Absatz', inline: false },
    { type: 'header', group: 'text', label: 'Überschrift', inline: false },
    { type: 'list', group: 'text', label: 'Liste', inline: false },
  ],
  mergeFields: [
    { path: 'contact.name', sample: 'Marie Fuhrmann' },
    { path: 'creditNote.number', sample: 'GS-2026-1' },
    { path: 'creditNote.invoiceNumber', sample: 'RE-2026-5' },
  ],
}

const meta = {
  title: 'Bildschirme/Rechnungen/Gutschrift',
  component: CreditNote,
  parameters: { layout: 'fullscreen', url: '/rechnungen/in-5/gutschrift/neu', page: { letterEditor } },
  args: {
    invoice: {
      row: {
        id: 'in-5',
        number: 'RE-2026-5',
        issuedOn: '2026-11-02',
        projectId: 'p-fuhrmann',
        projectName: 'Hochzeit Marie & Adrian',
        contact: 'Marie Fuhrmann',
        amount: 68_500,
        paidAmount: 68_500,
        credited: 0,
        dueOn: '2026-11-16',
        status: 'paid',
        statusLabel: 'Bezahlt',
        overdueDays: null,
        settledOn: null,
        cancelledOn: null,
        sentOn: '2026-11-02',
        viewedOn: null,
        views: [],
        hasDocument: true,
        openInstallments: [],
        creditNotes: [],
      },
      recipient: 'Marie Fuhrmann',
      quoteId: 'q-3',
      quoteNumber: 'AN-2026-3',
      items: [
        { title: 'Restzahlung', description: null, quantity: 1, unitPrice: 29_500, total: 29_500, gross: 29_500 },
        { title: 'Album', description: 'Leinen, 30 x 30 cm', quantity: 1, unitPrice: 39_000, total: 39_000, gross: 39_000 },
      ],
      net: 68_500,
      vat: 0,
      gross: 68_500,
      vatRate: '0 %',
      events: [{ kind: 'created', on: '2026-11-02', amount: null }],
    },
    creditable: 68_500,
    draft: null,
    studioName: '50mm freunde',
  },
} satisfies Meta<typeof CreditNote>

export default meta
type Story = StoryObj<typeof meta>

export const Leer: Story = {}

/** Schon teilweise gutgeschrieben: mehr als der Rest lässt sich nicht stellen. */
export const UeberRest: Story = { args: { creditable: 39_000 } }

export const MitEntwurf: Story = {
  args: {
    draft: { taken: [false, true], issued_on: '2026-11-10', reason: 'Album nicht geliefert', intro: '', outro: '', send: false },
  },
}
