import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Show from './Show.vue'

const page = {
  auth: {
    user: { id: 'u1', name: 'Christian Zepter', email: 'christian@atelier-nordlicht.de' },
    can: ['manage_finances'] as App.Enums.Capability[],
  },
}

const report: App.Finance.Data.EuerReportData = {
  year: 2026,
  vatMode: 'standard',
  lockedAt: null,
  operatingIncome: 380_000,
  creditNotes: 50_000,
  collectedVat: 72_200,
  assetSales: 30_000,
  income: 482_200,
  categories: [
    { key: 'software_abos', label: 'Software & Abos', euerLine: 'sonstige_betriebsausgaben', amount: 6_512, count: 3 },
    { key: 'hosting_domains', label: 'Hosting & Domains', euerLine: 'sonstige_betriebsausgaben', amount: 10_000, count: 1 },
    { key: 'weiterbildung', label: 'Weiterbildung', euerLine: 'sonstige_betriebsausgaben', amount: 45_000, count: 1 },
  ],
  depreciation: 285_800,
  residualBookValue: 59_500,
  paidInputVat: 43_617,
  expenses: 503_447,
  profit: -21_247,
  checklist: { withoutReceipt: 1, drafts: 3, openInvoices: 1, openInvoicesRemaining: 142_800, foreignWithoutEur: 1 },
}

const meta = {
  title: 'Bildschirme/Finanzen/EÜR',
  component: Show,
  parameters: { layout: 'fullscreen', url: '/finanzen/euer', page },
  args: { report, years: [2026, 2025, 2024], lockable: false },
} satisfies Meta<typeof Show>

export default meta
type Story = StoryObj<typeof meta>

export const Offen: Story = {}

export const GesperrtKleinunternehmer: Story = {
  args: {
    report: {
      ...report,
      year: 2025,
      vatMode: 'small_business',
      lockedAt: '2026-01-05',
      collectedVat: 0,
      paidInputVat: 0,
      checklist: { withoutReceipt: 0, drafts: 0, openInvoices: 0, openInvoicesRemaining: 0, foreignWithoutEur: 0 },
    },
  },
}

export const VergangenesJahr: Story = {
  args: { report: { ...report, year: 2024, checklist: { ...report.checklist, drafts: 1, withoutReceipt: 0, openInvoices: 0, foreignWithoutEur: 0 } }, lockable: true },
}

export const Leer: Story = {
  args: {
    report: {
      ...report,
      year: 2023,
      operatingIncome: 0, creditNotes: 0, collectedVat: 0, assetSales: 0, income: 0, categories: [],
      depreciation: 0, residualBookValue: 0, paidInputVat: 0, expenses: 0, profit: 0,
      checklist: { withoutReceipt: 0, drafts: 0, openInvoices: 0, openInvoicesRemaining: 0, foreignWithoutEur: 0 },
    },
  },
}
