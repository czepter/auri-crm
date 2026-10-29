import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

/*
 * #480: die Rechnung als eigenes Dokument. Die Zeile darunter ist ihre
 * Gutschrift — sie steht hier mit, weil der Betrag der Rechnung sonst etwas
 * behauptet, was nicht mehr stimmt.
 */
const page = {
  auth: {
    user: { id: 'u1', name: 'Christian Zepter', email: 'christian@atelier-nordlicht.de' },
    can: ['manage_contracts', 'manage_quotes'] as App.Enums.Capability[],
  },
}

const meta = {
  title: 'Bildschirme/Rechnungen',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/rechnungen', page },
  args: {
    exportYears: [2026, 2025],
    invoices: [
      {
        id: 'in-14',
        number: 'RE-2026-014',
        issuedOn: '2026-09-04',
        projectId: 'p-rahn',
        projectName: 'Rahn, Marie & Jonas',
        contact: 'Marie Rahn',
        amount: 150_000,
        paidAmount: 140_000,
        credited: 10_000,
        dueOn: '2026-09-18',
        status: 'partial',
        statusLabel: 'Teilweise bezahlt',
        sentOn: '2026-09-04',
        viewedOn: '2026-09-05',
        views: [{ id: 'dv-1', viewedAt: '2026-09-05T08:12:00+02:00', via: 'portal', device: 'iPhone · Safari', network: '3fa91c', counts: true }],
        hasDocument: true,
        openInstallments: [{ id: 'ra-2', remaining: 10_000 }],
        creditNotes: [
          {
            id: 'gs-2',
            number: 'GS-2026-002',
            amount: 10_000,
            issuedOn: '2026-09-11',
            hasDocument: true,
          },
        ],
      },
      {
        id: 'in-15',
        number: 'RE-2026-015',
        issuedOn: '2026-09-15',
        projectId: 'p-brandt',
        projectName: 'Brandt, Familie',
        contact: 'Lena Brandt',
        amount: 90_000,
        paidAmount: 30_000,
        credited: 0,
        dueOn: '2026-09-29',
        status: 'partial',
        statusLabel: 'Teilweise bezahlt',
        sentOn: '2026-09-15',
        viewedOn: null,
        views: [],
        hasDocument: true,
        openInstallments: [{ id: 'ra-3', remaining: 60_000 }],
        creditNotes: [],
      },
      {
        id: 'in-16',
        number: 'RE-2026-016',
        issuedOn: '2026-08-20',
        projectId: null,
        projectName: null,
        contact: 'Jonas Gast',
        amount: 35_700,
        paidAmount: 0,
        credited: 0,
        dueOn: '2026-09-03',
        status: 'overdue',
        statusLabel: 'Überfällig',
        sentOn: '2026-08-20',
        viewedOn: '2026-08-21',
        views: [{ id: 'dv-2', viewedAt: '2026-08-21T19:40:00+02:00', via: 'portal', device: 'Mac · Chrome', network: 'b27e04', counts: true }],
        hasDocument: true,
        openInstallments: [{ id: 'ra-4', remaining: 35_700 }],
        creditNotes: [],
      },
    ],
  },
} satisfies Meta<typeof Index>

export default meta

type Story = StoryObj<typeof meta>

export const Liste: Story = {}

export const Leer: Story = {
  args: { invoices: [], exportYears: [] },
}
