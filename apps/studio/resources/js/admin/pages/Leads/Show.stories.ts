import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Show from './Show.vue'
import {
    conflicts,
    conversation,
    lead,
    leadHeader,
    leadPeople,
    leadQuote,
    leadShoots,
    leadTasks,
    leadThread,
    packages,
    presets,
    queue,
    sources,
    stages,
    types,
    week,
} from '../__stories__/fixtures'

const meta = {
    title: 'Bildschirme/Anfrage',
    component: Show,
    parameters: { layout: 'fullscreen', url: `/anfragen/${lead.id}` },
    args: {
        tab: 'overview',
        header: leadHeader,
        lead,
        queue,
        people: leadPeople,
        shoots: leadShoots,
        quote: leadQuote,
        supersededQuotes: [],
        packages,
        tasks: leadTasks,
        thread: leadThread,
        types,
        sources,
        presets,
        week,
        conflicts,
        stages,
        conversation,
        quotes: [],
        payments: null,
    },
} satisfies Meta<typeof Show>

export default meta
type Story = StoryObj<typeof meta>

/** Angebot draußen, Antwort steht aus. */
export const Uebersicht: Story = {}

/** Derselbe Fall auf dem Angebotsreiter, Zahlungen aus dem Angebot. */
export const Angebote: Story = {
    args: {
        tab: 'quotes',
        quotes: [leadQuote],
        payments: {
            number: leadQuote.number,
            rows: [
                { label: 'Bei der Zusage', amount: 170000 },
                { label: 'Nach der Galerie', amount: 178000 },
            ],
        },
    },
}

/** Zuletzt hat die Kundin geschrieben. */
export const Nachrichten: Story = {
    args: { tab: 'messages' },
}

/** Abgesagt und geschlossen: kein Stufenmenü mehr, nur der Grund im Kopf. */
export const Verloren: Story = {
    args: {
        header: { ...leadHeader, outcomeLabel: 'Verloren', lostReason: 'Termin anderweitig vergeben' },
        lead: { ...lead, outcomeLabel: 'Verloren', lostReason: 'Termin anderweitig vergeben' },
    },
}

const note = (index: number) => ({
    key: `n-${index}`,
    at: `2026-09-${String(10 + index).padStart(2, '0')}T11:00:00+02:00`,
    body: `Notiz ${index + 1} zum Gespräch`,
    author: 'Christian Zepter',
    system: false,
    note: `note-${index}`,
})

/** Vier Termine, sieben Aufgaben (eine überfällig, im Schritt-Feld), fünf Notizen: Kappung und "Alle ansehen" greifen. */
export const Voll: Story = {
    args: {
        shoots: Array.from({ length: 4 }, (_, index) => ({
            ...leadShoots[0],
            id: `s-${index}`,
            isPrimary: index === 0,
            date: `2027-0${6 + index}-12`,
        })),
        tasks: Array.from({ length: 7 }, (_, index) => ({
            ...leadTasks[0],
            id: `t-${index}`,
            title: `Aufgabe ${index + 1}`,
            isOverdue: index === 0,
        })),
        thread: [...Array.from({ length: 4 }, (_, index) => note(index)), ...leadThread],
    },
}

/** Nichts erfasst: Kachelreihe statt leerer Karten, Anfragetext als Eingabe. */
export const Leer: Story = {
    args: {
        shoots: [],
        quote: null,
        tasks: [],
        thread: [],
        lead: { ...lead, inquiryText: null },
        header: { ...leadHeader, primaryDate: null, shootCount: 0 },
    },
}
