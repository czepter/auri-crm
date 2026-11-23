import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const page = {
  auth: {
    user: { id: 'u1', name: 'Christian Zepter', email: 'christian@atelier-nordlicht.de' },
    can: ['manage_finances'] as App.Enums.Capability[],
  },
}

const categories = [
  { id: 'c1', key: 'software_abos', label: 'Software & Abos', euerLine: 'sonstige_betriebsausgaben', skr04Account: null },
  { id: 'c2', key: 'hosting_domains', label: 'Hosting & Domains', euerLine: 'sonstige_betriebsausgaben', skr04Account: null },
  { id: 'c3', key: 'fotozubehoer', label: 'Fotozubehör', euerLine: 'sonstige_betriebsausgaben', skr04Account: null },
]

const row = (over: Partial<App.Finance.Data.ExpenseRowData>): App.Finance.Data.ExpenseRowData => ({
  id: 'e1',
  vendor: 'Nordhost Hosting GmbH',
  description: 'Webhosting Jahrespaket',
  invoiceNo: null,
  invoiceDate: '2026-01-15',
  paidAt: '2026-01-15',
  status: 'booked',
  categoryId: 'c2',
  categoryLabel: 'Hosting & Domains',
  businessShare: 100,
  currency: 'EUR',
  gross: 11_900,
  grossEur: 11_900,
  hasReceipt: true,
  isAsset: false,
  ...over,
})

const meta = {
  title: 'Bildschirme/Finanzen/Ausgaben',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/finanzen/ausgaben', page },
  args: {
    year: 2026,
    years: [2026, 2025, 2024],
    locked: false,
    totals: { sum: 61_380, business: 48_955, drafts: 2 },
    filters: { kategorie: null, status: null, anteil: null, ohneBeleg: false },
    categories,
    rows: [
      row({ id: 'e12', vendor: 'Lichtwerk Software Ltd.', description: 'Bildbearbeitung Abo', status: 'draft', paidAt: null, categoryId: null, categoryLabel: null, gross: 2_380, grossEur: 2_380 }),
      row({ id: 'e13', vendor: 'Bürobedarf Altona', description: 'Ordner und Mappen', status: 'draft', paidAt: null, hasReceipt: false, gross: 4_760, grossEur: 4_760 }),
      row({ id: 'e1' }),
      row({ id: 'e3', vendor: 'Pixelnetz Cloud Inc.', description: 'Cloud-Speicher', currency: 'USD', gross: 4_900, grossEur: 4_512, categoryLabel: 'Software & Abos', paidAt: '2026-02-20' }),
      row({ id: 'e5', vendor: 'Mobilfunk Nord AG', description: 'Mobilfunk', businessShare: 50, gross: 5_950, grossEur: 5_950, categoryLabel: 'Sonstiges', paidAt: '2026-03-05' }),
      row({ id: 'e6', vendor: 'Papierhaus Elbe', description: 'Fotodruckpapier', hasReceipt: false, gross: 3_570, grossEur: 3_570, categoryLabel: 'Fotozubehör', paidAt: '2026-04-02' }),
      row({ id: 'e11', vendor: 'Gesellschaft für Foto- und Medienbedarf, Vertrieb und Dienstleistungen im Großraum Hamburg und Umgebung mbH', description: 'Langer Beschreibungstext zur Prüfung der Tabellenzeile und des Formulars. '.repeat(3), businessShare: 0, gross: 15_000, grossEur: 15_000, categoryLabel: 'Sonstiges', paidAt: '2026-07-15' }),
      row({ id: 'e14', vendor: 'Nordhost Hosting GmbH', description: 'Doppelt abgebucht', status: 'ignored', paidAt: '2026-01-16' }),
    ],
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

export const Liste: Story = {}

export const Gesperrt: Story = {
  args: { locked: true, totals: { sum: 11_900, business: 11_900, drafts: 0 }, rows: [row({ id: 'e1' })] },
}

export const Leer: Story = {
  args: { year: 2023, totals: { sum: 0, business: 0, drafts: 0 }, rows: [] },
}

export const GefiltertOhneTreffer: Story = {
  args: { filters: { kategorie: 'werbung', status: null, anteil: null, ohneBeleg: false }, rows: [] },
}
