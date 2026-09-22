import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Shoots from './Shoots.vue'
import { header, shoots } from '../__stories__/fixtures'

const engagement: App.Crm.Data.ShootData = {
    ...shoots[0],
    id: 's-rahn-verlobung',
    kind: 'Verlobungsshooting',
    label: 'Verlobungsshooting',
    date: '2026-05-30',
    callTime: '17:00',
    endTime: '19:00',
    venue: 'Elbstrand, Hamburg',
    venueName: 'Elbstrand',
    venueCity: 'Hamburg',
    status: 'delivered',
    statusLabel: 'Übergeben',
    isPrimary: false,
    forwardStatuses: [],
}

const cancelled: App.Crm.Data.ShootData = {
    ...engagement,
    id: 's-rahn-abgesagt',
    date: '2026-06-20',
    status: 'cancelled',
    statusLabel: 'Abgesagt',
    isCancelled: true,
    cancelledReason: 'Termin vom Paar verschoben',
    forwardStatuses: [],
}

const meta = {
    title: 'Bildschirme/Shootings',
    component: Shoots,
    parameters: { layout: 'fullscreen', url: `/auftraege/${header.id}/shootings` },
    args: {
        header,
        shoots: [...shoots, engagement],
        crewAvailability: {
            's-rahn': [
                { id: 'u2', name: 'Mira Halvorsen', status: 'free', note: null },
                { id: 'u3', name: 'Paul Riedel', status: 'busy', note: 'Hochzeit Ahrens & Kroll' },
            ],
        },
        soloStudio: false,
        shootKinds: ['Verlobungsshooting', 'Hochzeitstag'],
    },
} satisfies Meta<typeof Shoots>

export default meta
type Story = StoryObj<typeof meta>

/** Der Haupttermin steht bevor, das Verlobungsshooting ist übergeben. */
export const ZweiTermine: Story = {}

/** Ein abgesagter Termin neben den übrigen. */
export const Abgesagt: Story = {
    args: { shoots: [...shoots, cancelled] },
}

/** Auftrag ohne Termin. */
export const Keine: Story = {
    args: { shoots: [], crewAvailability: {} },
}
