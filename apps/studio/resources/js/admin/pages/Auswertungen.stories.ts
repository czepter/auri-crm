import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Auswertungen from './Auswertungen.vue'

const meta = {
  title: 'Bildschirme/Auswertungen',
  component: Auswertungen,
  parameters: { layout: 'fullscreen', url: '/auswertungen' },
  args: {
    window: { from: '2026-01-01', to: '2026-12-31' },
    filters: { period: '12m', type: null },
    periods: [
      { value: '12m', label: 'Letzte 12 Monate' },
      { value: '6m', label: 'Letzte 6 Monate' },
      { value: 'jahr', label: 'Dieses Jahr' },
    ],
    projectTypes: [
      { value: 'wedding', label: 'Hochzeit' },
      { value: 'portrait', label: 'Portrait' },
      { value: 'other', label: 'Sonstige' },
    ],
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
      projects: 4,
      quotes: 2,
      months: [
        { month: '2026-07', due: 213000, received: 213000, open: 0 },
        { month: '2026-08', due: 255600, received: 170400, open: 0 },
        { month: '2026-09', due: 426000, received: 127800, open: 852000 },
        { month: '2026-10', due: 298200, received: 0, open: 426000 },
        { month: '2026-11', due: 170400, received: 0, open: 0 },
        { month: '2026-12', due: 85200, received: 0, open: 0 },
      ],
    },
    cashFlowPast: {
      projects: 3,
      months: [
        { month: '2026-04', received: 127800 },
        { month: '2026-05', received: 0 },
        { month: '2026-06', received: 213000 },
        { month: '2026-07', received: 213000 },
        { month: '2026-08', received: 170400 },
        { month: '2026-09', received: 127800 },
      ],
    },
    revenueByType: [
      { type: 'wedding', label: 'Hochzeit', projects: 7, revenue: 3834000, average: 547714 },
      { type: 'other', label: 'Sonstige', projects: 1, revenue: 298200, average: 298200 },
      { type: 'portrait', label: 'Portrait', projects: 3, revenue: 554000, average: 184667 },
    ],
    steps: [
      { key: 'new', label: 'Neu', leads: 47, lost: 0, drop: null },
      { key: 'contacted', label: 'Kontaktiert', leads: 39, lost: 8, drop: 17 },
      { key: 'quote_sent', label: 'Angebot', leads: 21, lost: 18, drop: 46 },
      { key: 'quote_accepted', label: 'Angebot angenommen', leads: 11, lost: 10, drop: 48 },
      { key: 'booked', label: 'Gebucht', leads: 11, lost: 0, drop: 0 },
    ],
    dwell: [
      { key: 'new', label: 'Neu', days: 1.5, moves: 39 },
      { key: 'contacted', label: 'Kontaktiert', days: 4.2, moves: 21 },
      { key: 'quote_sent', label: 'Angebot', days: 11.4, moves: 11 },
      { key: 'quote_accepted', label: 'Angebot angenommen', days: 2.1, moves: 11 },
    ],
    responseTimes: {
      buckets: [
        { key: 'day', label: 'unter 24 Std.', leads: 21 },
        { key: 'days3', label: '1–3 Tage', leads: 12 },
        { key: 'days7', label: '3–7 Tage', leads: 4 },
        { key: 'later', label: 'über 7 Tage', leads: 2 },
      ],
      medianHours: 14.5,
      unanswered: 8,
    },
    shootsByMonth: [
      { month: '2026-03', shoots: 2 },
      { month: '2026-04', shoots: 4 },
      { month: '2026-05', shoots: 6 },
      { month: '2026-06', shoots: 9 },
      { month: '2026-07', shoots: 7 },
      { month: '2026-08', shoots: 5 },
      { month: '2026-09', shoots: 4 },
    ],
    leadTimes: {
      buckets: [
        { key: 'q1', label: 'unter 3 Mon.', projects: 2 },
        { key: 'q2', label: '3–6 Mon.', projects: 3 },
        { key: 'q3', label: '6–9 Mon.', projects: 4 },
        { key: 'q4', label: '9–12 Mon.', projects: 1 },
        { key: 'later', label: 'über 12 Mon.', projects: 1 },
      ],
      median: '7,2 Mon.',
      withoutDate: 1,
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
    cashFlow: { overdue: 0, projects: 0, quotes: 0, months: [] },
    cashFlowPast: { projects: 0, months: [] },
    revenueByType: [],
    steps: [],
    dwell: [],
    responseTimes: { buckets: [], medianHours: null, unanswered: 0 },
    shootsByMonth: [],
    leadTimes: { buckets: [], median: null, withoutDate: 0 },
  },
}

/** Ein Monat Geschichte: kein Vorjahr, also keine Vergleichszeile an den Kennzahlen. */
export const ErstesJahr: Story = {
  args: {
    funnelByMonth: [{ month: '2026-09', captured: 11, booked: 1, rate: 9 }],
    sources: [
      { source: 'referral', label: 'Empfehlung', captured: 5, booked: 1, revenue: 150000 },
      { source: 'website', label: 'Website', captured: 3, booked: 0, revenue: 0 },
      { source: 'instagram', label: 'Instagram', captured: 2, booked: 0, revenue: 0 },
    ],
    referrers: [{ name: 'Osterhoff, Familie', count: 1 }],
    lostReasons: [],
    acceptanceRate: 33,
    acceptanceRatePrior: null,
    revenue: { projects: 1, revenue: 150000, averagePerProject: 150000 },
    revenuePrior: { projects: 0, revenue: 0, averagePerProject: null },
    perContact: [{ name: 'Meier & Kowalski', projects: 1, revenue: 150000 }],
    cashFlow: {
      overdue: 150000,
      projects: 1,
      quotes: 1,
      months: [{ month: '2026-09', due: 0, received: 0, open: 350000 }],
    },
    cashFlowPast: { projects: 0, months: [{ month: '2026-09', received: 0 }] },
    revenueByType: [{ type: 'wedding', label: 'Hochzeit', projects: 1, revenue: 150000, average: 150000 }],
    steps: [
      { key: 'new', label: 'Neu', leads: 11, lost: 0, drop: null },
      { key: 'contacted', label: 'Kontaktiert', leads: 6, lost: 5, drop: 45 },
      { key: 'quote_sent', label: 'Angebot', leads: 3, lost: 3, drop: 50 },
      { key: 'quote_accepted', label: 'Angebot angenommen', leads: 1, lost: 2, drop: 67 },
      { key: 'booked', label: 'Gebucht', leads: 1, lost: 0, drop: 0 },
    ],
    dwell: [
      { key: 'new', label: 'Neu', days: 2.4, moves: 6 },
      { key: 'contacted', label: 'Kontaktiert', days: 6.1, moves: 3 },
    ],
    responseTimes: {
      buckets: [
        { key: 'day', label: 'unter 24 Std.', leads: 3 },
        { key: 'days3', label: '1–3 Tage', leads: 2 },
        { key: 'days7', label: '3–7 Tage', leads: 1 },
        { key: 'later', label: 'über 7 Tage', leads: 0 },
      ],
      medianHours: 20.5,
      unanswered: 5,
    },
    shootsByMonth: [{ month: '2027-06', shoots: 1 }],
    leadTimes: {
      buckets: [
        { key: 'q1', label: 'unter 3 Mon.', projects: 0 },
        { key: 'q2', label: '3–6 Mon.', projects: 0 },
        { key: 'q3', label: '6–9 Mon.', projects: 1 },
        { key: 'q4', label: '9–12 Mon.', projects: 0 },
        { key: 'later', label: 'über 12 Mon.', projects: 0 },
      ],
      median: '8,4 Mon.',
      withoutDate: 0,
    },
  },
}
