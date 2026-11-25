import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Invoices from './Invoices.vue'
import { header } from '../__stories__/fixtures'

const rechnung = (
    row: Pick<App.Payments.Data.InvoiceRowData, 'id' | 'number' | 'issuedOn' | 'amount' | 'status' | 'statusLabel'> &
        Partial<App.Payments.Data.InvoiceRowData>,
): App.Payments.Data.InvoiceRowData => ({
    projectId: header.id,
    projectName: header.name,
    contact: 'Jana Rahn',
    paidAmount: 0,
    credited: 0,
    dueOn: null,
    overdueDays: null,
    settledOn: null,
    cancelledOn: null,
    sentOn: null,
    viewedOn: null,
    views: [],
    hasDocument: true,
    openInstallments: [],
    creditNotes: [],
    ...row,
})

const anzahlung = rechnung({
    id: 'inv-1',
    number: 'R-2026-014',
    issuedOn: '2026-06-02',
    amount: 90000,
    paidAmount: 90000,
    credited: 20000,
    status: 'paid',
    statusLabel: 'Bezahlt',
    creditNotes: [{ id: 'cn-1', number: 'G-2026-003', amount: 20000, issuedOn: '2026-07-10', reason: null, hasDocument: true }],
})

const rest = rechnung({
    id: 'inv-2',
    number: 'R-2026-031',
    issuedOn: '2026-09-04',
    amount: 160000,
    dueOn: '2026-09-18',
    status: 'open',
    statusLabel: 'Offen',
})

const meta = {
    title: 'Bildschirme/Auftrag Rechnungen',
    component: Invoices,
    parameters: { layout: 'fullscreen', url: `/auftraege/${header.id}/rechnungen` },
    args: { header, invoices: [anzahlung, rest] },
} satisfies Meta<typeof Invoices>

export default meta
type Story = StoryObj<typeof meta>

/** Eine bezahlte Rechnung mit Gutschrift, eine offene. */
export const MitGutschrift: Story = {}

/** Nichts gestellt: nur die Schaltfläche. */
export const Leer: Story = {
    args: { invoices: [] },
}
