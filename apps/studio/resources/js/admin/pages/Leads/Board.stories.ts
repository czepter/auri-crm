import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Board from './Board.vue'
import { conflicts, queue, stages } from '../__stories__/fixtures'

// Die Liegezeit steht auf der Karte, also muss sie mitaltern: ein
// festes Datum hier zeigt nach einer Woche einen anderen Zustand als
// daysInStage daneben behauptet.
const daysAgo = (days: number) =>
    new Date(Date.now() - days * 86_400_000).toISOString()

const cards: App.Crm.Data.LeadCardData[] = [
    {
        id: 'p-osterhoff',
        leadId: 'l-osterhoff',
        type: 'Hochzeit',
        typeKey: 'wedding',
        names: 'Familie Osterhoff',
        date: '2027-04-24',
        shootCount: 1,
        venue: 'Standesamt und Garten',
        source: 'Website',
        stage: 'new',
        daysInStage: 6,
        stale: true,
        value: null,
        lastAt: daysAgo(6),
        conflicts: [],
    },
    {
        id: 'p-hinrichs',
        leadId: 'l-hinrichs',
        type: 'Porträt',
        typeKey: 'portrait',
        names: 'Nele Hinrichs',
        date: null,
        shootCount: 1,
        venue: null,
        source: 'Instagram',
        stage: 'contacted',
        daysInStage: 2,
        stale: false,
        value: null,
        lastAt: daysAgo(2),
        conflicts: [],
    },
    {
        id: 'p-brandt',
        leadId: 'l-brandt',
        type: 'Hochzeit',
        typeKey: 'wedding',
        names: 'Lena und Tom Brandt',
        date: '2027-06-12',
        shootCount: 1,
        venue: 'Gut Basthorst',
        source: 'Website',
        stage: 'quote_sent',
        daysInStage: 4,
        stale: true,
        value: 348000,
        lastAt: daysAgo(4),
        conflicts,
    },
    {
        id: 'p-ahrens',
        leadId: 'l-kroll',
        type: 'Hochzeit',
        typeKey: 'wedding',
        names: 'Marie Ahrens und David Kroll',
        date: '2026-07-25',
        shootCount: 2,
        venue: 'Kaispeicher, Hamburg',
        source: 'Hochzeitsmesse',
        stage: 'quote_sent',
        daysInStage: 11,
        stale: true,
        value: 412000,
        lastAt: daysAgo(11),
        conflicts: [],
    },
    {
        id: 'p-rahn',
        leadId: 'l-rahn',
        type: 'Hochzeit',
        typeKey: 'wedding',
        names: 'Jana und Michael Rahn',
        date: '2026-09-13',
        shootCount: 1,
        venue: 'Gut Basthorst',
        source: 'Empfehlung',
        stage: 'quote_accepted',
        daysInStage: 2,
        stale: false,
        value: 426000,
        lastAt: daysAgo(2),
        conflicts: [],
    },
]

const columns = stages.map((stage) => ({
    stage: stage.value,
    label: stage.label,
    cards: cards.filter((card) => card.stage === stage.value),
    total: cards.filter((card) => card.stage === stage.value).reduce((sum, card) => sum + (card.value ?? 0), 0),
}))

const groups = queue.map((group) => ({
    key: group.key,
    rows: group.rows
        .map((row) => cards.find((card) => card.leadId === row.id))
        .filter((card): card is App.Crm.Data.LeadCardData => card !== undefined),
}))

const meta = {
    title: 'Bildschirme/Anfragen',
    component: Board,
    parameters: { layout: 'fullscreen', url: '/anfragen' },
    args: { columns, groups, queue, view: 'board', stageSettings: null, settingsSelection: null, returnTo: null, workflow: null },
} satisfies Meta<typeof Board>

export default meta
type Story = StoryObj<typeof meta>

/** Anfragen über alle sechs Stufen, eine davon mit Terminkonflikt. */
export const Board_: Story = { name: 'Board' }

/** Keine offene Anfrage. */
export const Leer: Story = {
    args: {
        columns: columns.map((column) => ({ ...column, cards: [], total: 0 })),
        groups: groups.map((group) => ({ ...group, rows: [] })),
        queue: queue.map((group) => ({ ...group, rows: [] })),
    },
}
