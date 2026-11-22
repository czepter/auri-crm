import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Edit from './Edit.vue'

const page = {
  auth: {
    user: { id: 'u1', name: 'Christian Zepter', email: 'christian@atelier-nordlicht.de' },
    can: ['manage_finances'] as App.Enums.Capability[],
  },
}

const expense: App.Finance.Data.ExpenseDetailData = {
  id: 'e1',
  vendor: 'Nordhost Hosting GmbH',
  description: 'Webhosting Jahrespaket',
  invoiceNo: 'NH-2026-0042',
  invoiceDate: '2026-01-10',
  paidAt: '2026-01-15',
  status: 'booked',
  categoryId: 'c2',
  businessShare: 100,
  currency: 'EUR',
  net: 10_000,
  vatRate: 1900,
  vat: 1_900,
  gross: 11_900,
  grossEur: 11_900,
  note: null,
  isSelfIssued: false,
  documents: [],
  asset: null,
}

const meta = {
  title: 'Bildschirme/Finanzen/Ausgabe',
  component: Edit,
  parameters: { layout: 'fullscreen', url: '/finanzen/ausgaben/e1', page },
  args: {
    expense,
    locked: false,
    categories: [
      { id: 'c1', key: 'software_abos', label: 'Software & Abos', euerLine: 'sonstige_betriebsausgaben', skr04Account: null },
      { id: 'c2', key: 'hosting_domains', label: 'Hosting & Domains', euerLine: 'sonstige_betriebsausgaben', skr04Account: null },
    ],
    gwgLimit: 80_000,
    afaCategories: [{ key: 'computer_software', label: 'Computer, Software', months: 12 }],
    vatModes: {},
    defaultVatMode: 'standard',
  },
} satisfies Meta<typeof Edit>

export default meta
type Story = StoryObj<typeof meta>

export const Gebucht: Story = {}

export const Neu: Story = { args: { expense: null } }

export const Entwurf: Story = {
  args: { expense: { ...expense, id: 'e12', status: 'draft', paidAt: null, categoryId: null, vendor: 'Lichtwerk Software Ltd.' } },
}

export const Verworfen: Story = { args: { expense: { ...expense, status: 'ignored' } } }

export const Anlage: Story = {
  args: {
    expense: {
      ...expense,
      description: 'Mobiler Rechner',
      net: 145_000,
      vat: 27_550,
      gross: 172_550,
      grossEur: 172_550,
      asset: {
        id: 'a1',
        inventoryNo: 'INV-26-001',
        name: 'Mobiler Rechner',
        acquiredAt: '2026-04-10',
        costNet: 145_000,
        costGross: 172_550,
        method: 'linear',
        usefulLifeMonths: 12,
        afaCategory: 'computer_software',
        disposedAt: null,
        disposalProceeds: null,
      },
    },
  },
}

export const Gesperrt: Story = { args: { locked: true, expense: Anlage.args!.expense } }
