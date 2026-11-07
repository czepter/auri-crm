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
