import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Auswertungen from './Auswertungen.vue'

const meta = {
  title: 'Bildschirme/Auswertungen',
  component: Auswertungen,
  parameters: { layout: 'fullscreen', url: '/auswertungen' },
  args: {
    window: { from: '2026-01-01', to: '2026-12-31' },
    funnelByMonth: [
      { month: '2026-01', captured: 3, booked: 1, rate: 33 },
      { month: '2026-02', captured: 2, booked: 0, rate: 0 },
      { month: '2026-03', captured: 5, booked: 1, rate: 20 },
      { month: '2026-04', captured: 7, booked: 2, rate: 29 },
      { month: '2026-05', captured: 9, booked: 2, rate: 22 },
      { month: '2026-06', captured: 8, booked: 2, rate: 25 },
      { month: '2026-07', captured: 6, booked: 1, rate: 17 },
      { month: '2026-08', captured: 4, booked: 1, rate: 25 },
      { month: '2026-09', captured: 3, booked: 1, rate: 33 },
    ],
    sources: [
      { source: 'instagram', label: 'Instagram', captured: 21, booked: 5, revenue: 2130000 },
      { source: 'referral', label: 'Empfehlung', captured: 16, booked: 4, revenue: 1704000 },
      { source: 'website', label: 'Website', captured: 10, booked: 2, revenue: 852000 },
    ],
    referrers: [
      { name: 'Gut Basthorst', count: 6 },
      { name: 'Lena Krohn', count: 3 },
      { name: 'Rahn, Marie & Jonas', count: 2 },
    ],
    lostReasons: [
      { outcome: 'lost', outcomeLabel: 'Verloren', reason: 'Budget', count: 9 },
      { outcome: 'lost', outcomeLabel: 'Verloren', reason: 'Anderer Fotograf', count: 5 },
      { outcome: 'declined', outcomeLabel: 'Abgesagt', reason: 'Termin vergeben', count: 4 },
    ],
    acceptanceRate: 56,
    acceptanceRatePrior: 48,
    revenue: { projects: 11, revenue: 4686000, averagePerProject: 426000 },
    revenuePrior: { projects: 8, revenue: 3129000, averagePerProject: 391125 },
    perContact: [
      { name: 'Rahn, Marie & Jonas', projects: 1, revenue: 426000 },
      { name: 'Krohn, Lena', projects: 2, revenue: 639000 },
      { name: 'Petersen, Ann-Kathrin', projects: 1, revenue: 298200 },
    ],
    cashFlow: {
      overdue: 85200,
      months: [
        { month: '2026-07', due: 213000, received: 213000, open: 0 },
        { month: '2026-08', due: 255600, received: 170400, open: 0 },
        { month: '2026-09', due: 426000, received: 127800, open: 852000 },
        { month: '2026-10', due: 298200, received: 0, open: 426000 },
        { month: '2026-11', due: 170400, received: 0, open: 0 },
        { month: '2026-12', due: 85200, received: 0, open: 0 },
      ],
    },
  },
} satisfies Meta<typeof Auswertungen>

export default meta
type Story = StoryObj<typeof meta>

/** Ein Jahr mit Vorjahr dahinter: jede Kennzahl hat ihren Beleg und ihren Vergleich. */
export const MitZahlen: Story = {}

/** Erstes Jahr, noch kein gebuchter Auftrag — der Bildschirm fällt auf die Funnel-Aktion zurück. */
export const OhneZahlen: Story = {
  args: {
    funnelByMonth: [],
    sources: [],
    referrers: [],
    lostReasons: [],
    acceptanceRate: null,
    acceptanceRatePrior: null,
    revenue: { projects: 0, revenue: 0, averagePerProject: null },
    revenuePrior: { projects: 0, revenue: 0, averagePerProject: null },
    perContact: [],
    cashFlow: { overdue: 0, months: [] },
  },
}
