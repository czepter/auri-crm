import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Show from './Show.vue'
import {
    bookedLeadHeader,
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
    workspace,
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
        workspace: null,
    },
} satisfies Meta<typeof Show>

export default meta
type Story = StoryObj<typeof meta>

/** Angebot draußen, Antwort steht aus. */
export const Uebersicht: Story = {}

/** Derselbe Fall auf dem Angebotsreiter, mit Katalog und Paketen daneben. */
export const Angebote: Story = {
    args: { tab: 'quotes', workspace },
}

/** Zuletzt hat die Kundin geschrieben. */
export const Nachrichten: Story = {
    args: { tab: 'messages' },
}

/** Nach dem Buchen: der Fall hängt am Auftrag, die Kopfaktion führt dorthin. */
export const Gebucht: Story = {
    args: {
        header: bookedLeadHeader,
        lead: { ...lead, booked: true, stage: 'booked', stageLabel: 'Gebucht' },
    },
}

/** Abgesagt und geschlossen: kein Stufenmenü mehr, nur der Grund im Kopf. */
export const Verloren: Story = {
    args: {
        header: { ...leadHeader, outcomeLabel: 'Verloren', lostReason: 'Termin anderweitig vergeben' },
        lead: { ...lead, outcomeLabel: 'Verloren', lostReason: 'Termin anderweitig vergeben' },
    },
}
