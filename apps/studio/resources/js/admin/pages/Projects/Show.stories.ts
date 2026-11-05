import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Show from './Show.vue'
import { header, people, presets, quote, shoots, sources, stages, tasks, thread } from '../__stories__/fixtures'

const scopeItems = quote.items.map((item) => ({
    id: item.id,
    title: item.title,
    quantity: item.quantity,
    unitPrice: item.unitPrice,
    state: null,
    wasUnitPrice: null,
}))

const fields = [
    {
        id: 'f-guests',
        key: 'guests',
        label: 'Gäste',
        type: 'number',
        typeLabel: 'Zahl',
        options: [],
        unit: 'Personen',
        required: false,
        allowNewOptions: false,
        isActive: true,
    },
    {
        id: 'f-ceremony',
        key: 'ceremony',
        label: 'Trauung',
        type: 'select',
        typeLabel: 'Auswahl',
        options: ['Standesamt', 'Kirchlich', 'Frei'],
        required: false,
        unit: null,
        allowNewOptions: true,
        isActive: true,
    },
]

const meta = {
    title: 'Bildschirme/Auftrag',
    component: Show,
    parameters: { layout: 'fullscreen', url: `/auftraege/${header.id}` },
    args: {
        header,
        shoots,
        thread,
        tasks,
        shootOptions: [{ value: 's-rahn', label: 'Hochzeit · 13.09.2026' }],
        staffOptions: [{ value: 'u1', label: 'Christian Zepter' }],
        anchors: [],
        quote,
        supersededQuotes: [],
        scope: { items: scopeItems, delta: 0, hasDraft: false },
        people,
        fields,
        fieldValues: { guests: 85, ceremony: 'Frei' },
        stages,
        sources,
        roles: [
            { value: 'primary', label: 'primary' },
            { value: 'partner', label: 'partner' },
            { value: 'vendor', label: 'vendor' },
        ],
        presets: { source: presets.source },
        stage: 'booked',
        source: 'referral',
        sourceOther: null,
        sourceLabel: 'Empfehlung',
        inquiry: {
            text: 'Wir heiraten am 13. September auf Gut Basthorst und suchen eine Reportage über den ganzen Tag.',
            typeLabel: 'Hochzeit',
            sourceLabel: 'Empfehlung',
            capturedVia: 'Telefonisch',
            capturedAt: '2026-02-14T10:20:00+01:00',
        },
        stalled: null,
    },
} satisfies Meta<typeof Show>

export default meta
type Story = StoryObj<typeof meta>

/** Gebucht, bezahlt zur Hälfte, Angebot angenommen. */
export const Auftrag: Story = {}

/** Das Shooting liegt zurück und steht seit elf Tagen auf demselben Stand. */
export const Stillstand: Story = {
    args: {
        stalled: {
            shootId: 's-rahn',
            label: 'Hochzeit Rahn',
            date: '2026-09-13',
            days: 11,
            status: 'Fotografiert',
            since: '2026-09-14',
            next: { key: 'culling', label: 'Auswahl' },
        },
    },
}

/** Auftrag ohne angenommenes Angebot: kein Leistungsumfang, kein Betrag. */
export const OhneAngebot: Story = {
    args: {
        quote: null,
        scope: { items: [], delta: 0, hasDraft: false },
    },
}
