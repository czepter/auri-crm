import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Show from './Show.vue'
import { header, presets, quote, shoots, sources, stages, tasks, thread } from '../__stories__/fixtures'

const installment = (id: string, label: string, dueOn: string, amount: number, status: string, paidAmount: number): App.Payments.Data.PaymentInstallmentData => ({
    id,
    amount,
    dueOn,
    status,
    statusLabel: status === 'paid' ? 'Bezahlt' : 'Offen',
    sortOrder: 0,
    label,
    settledVia: null,
    settledViaLabel: null,
    paidOn: status === 'paid' ? dueOn : null,
    paymentReference: null,
    paidAmount,
    remaining: amount - paidAmount,
    isLate: false,
    cancelledOn: null,
    invoiceId: null,
    invoiceNumber: null,
    invoicedOn: null,
    hasInvoiceDocument: false,
    creditNotes: [],
})

const paymentPlan: App.Payments.Data.PaymentPlanData = {
    id: 'pp-1',
    total: 470100,
    openTotal: 329100,
    reference: null,
    settledOn: null,
    installments: [
        installment('i-1', 'Anzahlung', '2026-03-02', 141000, 'paid', 141000),
        installment('i-2', 'Restbetrag', '2026-09-06', 329100, 'scheduled', 0),
    ],
}

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
        header: { ...header, paymentPlan },
        shoots,
        thread,
        tasks,
        shootOptions: [{ value: 's-rahn', label: 'Hochzeit · 13.09.2026' }],
        staffOptions: [{ value: 'u1', label: 'Christian Zepter' }],
        anchors: [],
        quote,
        supersededQuotes: [],
        scope: { items: scopeItems, delta: 0, hasDraft: false },
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

const crowd = Array.from({ length: 13 }, (_, index) => ({
    ...shoots[0],
    id: `s-${index}`,
    date: `2026-${String(9 + Math.floor(index / 5)).padStart(2, '0')}-${String(10 + (index % 5) * 4)}`,
    label: `Termin ${index + 1} mit langem Namen für den Stresstest`,
}))

/** Dreizehn Termine und vierzehn Aufgaben: Fenster und Kappung greifen. */
export const Voll: Story = {
    args: {
        shoots: crowd,
        tasks: Array.from({ length: 14 }, (_, index) => ({
            ...tasks[0],
            id: `t-${index}`,
            title: `Aufgabe ${index + 1}`,
            isOverdue: index === 0,
        })),
    },
}

/** Nichts erfasst: Kachelreihe statt leerer Karten, kein Kennzahlenband. */
export const Leer: Story = {
    args: {
        header: { ...header, paymentPlan: null, primaryDate: null },
        shoots: [],
        tasks: [],
        thread: [],
        quote: null,
        scope: { items: [], delta: 0, hasDraft: false },
        inquiry: { text: null, typeLabel: 'Hochzeit', sourceLabel: null, capturedVia: null, capturedAt: '2026-02-14T10:20:00+01:00' },
    },
}
