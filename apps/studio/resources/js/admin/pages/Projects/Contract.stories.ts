import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Contract from './Contract.vue'
import type { PaletteEntry } from '@/admin/documents/blocks/types'
import { header, people } from '../__stories__/fixtures'

const palette: PaletteEntry[] = [
    { type: 'paragraph', group: 'text', label: 'Absatz', inline: false, allowed: true, only: null },
    { type: 'header', group: 'text', label: 'Überschrift', inline: false, allowed: true, only: null },
    { type: 'list', group: 'text', label: 'Liste', inline: false, allowed: true, only: null },
    { type: 'delimiter', group: 'text', label: 'Trenner', inline: false, allowed: true, only: null },
    { type: 'table', group: 'layout', label: 'Tabelle', inline: false, allowed: true, only: null },
    { type: 'pagebreak', group: 'layout', label: 'Seitenumbruch', inline: false, allowed: true, only: null },
    { type: 'clause', group: 'contract', label: 'Klausel', inline: false, allowed: true, only: null },
    { type: 'signature', group: 'contract', label: 'Unterschriften', inline: false, allowed: true, only: null },
    { type: 'finePrint', group: 'contract', label: 'Kleingedrucktes', inline: false, allowed: true, only: null },
    { type: 'cancellation', group: 'contract', label: 'Ausfallstaffel', inline: false, allowed: true, only: null },
    { type: 'paymentPlan', group: 'data', label: 'Zahlungsplan', inline: false, allowed: true, only: null },
    { type: 'lineItems', group: 'data', label: 'Positionen', inline: false, allowed: false, only: 'Angebot und Rechnung' },
    { type: 'mergeField', group: 'data', label: 'Feld', inline: true, allowed: true, only: null },
]

const mergeFields = [
    { path: 'project.name', sample: 'Hochzeit Meier & Kowalski' },
    { path: 'contact.name', sample: 'Lena Meier' },
    { path: 'shoot.date', sample: '14.06.2026' },
    { path: 'studio.name', sample: 'Atelier Nordlicht Fotografie GmbH' },
    { path: 'quote.vatNote', sample: 'Alle Preise verstehen sich zzgl. 19 % Umsatzsteuer.' },
]

const blocks = {
    blocks: [
        { type: 'header', data: { text: 'Fotografievertrag', level: 2 } },
        {
            type: 'paragraph',
            data: {
                text: 'Zwischen {{ studio.name }} und {{ contact.name }} über die Reportage am {{ shoot.date }} auf Gut Basthorst.',
            },
        },
        { type: 'clause', data: { title: 'Leistungsumfang', text: 'Ganztägige Begleitung bis 23:00 Uhr, Zweitfotografin ab 14:00 Uhr.' } },
        { type: 'paymentPlan', data: {} },
        { type: 'signature', data: { rows: [] } },
    ],
}

const fassung: App.Contracts.Data.ContractData = {
    id: 'ct-rahn-v2',
    version: 2,
    state: 'valid',
    stateLabel: 'Gültig',
    status: 'rendered',
    statusLabel: 'Erzeugt',
    templateName: 'Hochzeitsvertrag',
    templateVersion: 3,
    body: '',
    blocks,
    renderedAt: '2026-02-27T09:40:00+01:00',
    sentAt: '2026-02-27T09:45:00+01:00',
    downloadUrl: '/vertraege/ct-rahn-v2.pdf',
    signDeadlineOn: '2026-03-13',
    signers: [
        { id: 'cs-jana', name: 'Jana Rahn', role: 'Auftraggeberin', signedAt: '2026-03-01T18:12:00+01:00', remindedAt: null },
        { id: 'cs-michael', name: 'Michael Rahn', role: 'Auftraggeber', signedAt: null, remindedAt: '2026-03-08T09:00:00+01:00' },
    ],
}

const vorgaenger: App.Contracts.Data.ContractData = {
    ...fassung,
    id: 'ct-rahn-v1',
    version: 1,
    state: 'superseded',
    stateLabel: 'Ersetzt',
    renderedAt: '2026-02-20T16:00:00+01:00',
    sentAt: null,
    downloadUrl: '/vertraege/ct-rahn-v1.pdf',
    signers: [],
}

const meta = {
    title: 'Bildschirme/Auftrag Vertrag',
    component: Contract,
    parameters: { layout: 'fullscreen', url: `/auftraege/${header.id}/vertrag` },
    args: {
        header,
        templates: [
            { value: 'dt-hochzeit', label: 'Hochzeitsvertrag · v3' },
            { value: 'dt-portrait', label: 'Porträtvertrag · v1' },
        ],
        contract: fassung,
        versions: [fassung, vorgaenger],
        contacts: [],
        palette,
        mergeFields,
    },
} satisfies Meta<typeof Contract>

export default meta
type Story = StoryObj<typeof meta>

/** Fassung 2 ist versendet, Jana hat unterschrieben, Michael wurde erinnert. */
export const Versendet: Story = {}

/** Noch kein Vertrag erzeugt: nur die Vorlagenwahl. */
export const OhneVertrag: Story = {
    args: {
        contract: null,
        versions: [],
        contacts: people.map((person) => ({ value: person.id, label: person.name })),
    },
}
