import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Forms from './Forms.vue'
import { header } from '../__stories__/fixtures'

const feld = (
    row: Pick<App.Crm.Data.FormInstanceFieldData, 'id' | 'label' | 'type' | 'typeLabel'> &
        Partial<App.Crm.Data.FormInstanceFieldData>,
): App.Crm.Data.FormInstanceFieldData => ({
    required: false,
    help: null,
    options: null,
    currentAnswer: null,
    answeredAt: null,
    groupPhotos: null,
    fileFormats: null,
    fileMaxSize: null,
    fileMultiple: false,
    files: [],
    ...row,
})

const ablauf: App.Crm.Data.FormInstanceData = {
    id: 'fi-ablauf',
    name: 'Ablauf und Kontakte',
    sourceTemplateName: 'Hochzeit — Ablauf',
    updatedAt: '2026-08-20T17:40:00+02:00',
    sharedAt: '2026-08-12T09:00:00+02:00',
    fields: [
        feld({
            id: 'ff-1',
            label: 'Adresse Getting Ready',
            type: 'short_text',
            typeLabel: 'Kurzantwort',
            required: true,
            currentAnswer: 'Hotel Gut Basthorst, Zimmer 4',
            answeredAt: '2026-08-14T19:22:00+02:00',
        }),
        feld({
            id: 'ff-2',
            label: 'Trauzeugen',
            type: 'short_text',
            typeLabel: 'Kurzantwort',
            currentAnswer: 'Sina Kluge, Robert Rahn',
            answeredAt: '2026-08-14T19:24:00+02:00',
        }),
        feld({
            id: 'ff-3',
            label: 'Erster Tanz',
            type: 'text',
            typeLabel: 'Freitext',
        }),
    ],
}

const buch: App.Crm.Data.FormInstanceData = {
    id: 'fi-buch',
    name: 'Fotobuch',
    sourceTemplateName: null,
    updatedAt: '2026-09-01T11:05:00+02:00',
    sharedAt: null,
    fields: [
        feld({ id: 'ff-4', label: 'Einbandfarbe', type: 'short_text', typeLabel: 'Kurzantwort' }),
        feld({ id: 'ff-5', label: 'Widmung auf Seite 1', type: 'text', typeLabel: 'Freitext' }),
    ],
}

const meta = {
    title: 'Bildschirme/Auftrag Fragebögen',
    component: Forms,
    parameters: { layout: 'fullscreen', url: `/auftraege/${header.id}/frageboegen` },
    args: {
        header,
        formInstances: [ablauf, buch],
        formTemplates: [
            { value: 'ft-hochzeit', label: 'Hochzeit — Ablauf' },
            { value: 'ft-portrait', label: 'Porträt — Vorbereitung' },
        ],
        fieldTypes: [
            { value: 'text', label: 'Freitext' },
            { value: 'short_text', label: 'Kurzantwort' },
            { value: 'boolean', label: 'Ja / Nein' },
            { value: 'select', label: 'Auswahl' },
            { value: 'date', label: 'Datum' },
            { value: 'time', label: 'Uhrzeit' },
            { value: 'name_list', label: 'Namensliste' },
            { value: 'group_photos', label: 'Gruppenfotos' },
            { value: 'file', label: 'Datei' },
        ],
    },
} satisfies Meta<typeof Forms>

export default meta
type Story = StoryObj<typeof meta>

/** Freigegebener Fragebogen, eine Frage ohne Antwort, daneben ein noch nicht freigegebener. */
export const Freigegeben: Story = {}

/** Nur der Entwurf: nichts ist freigegeben, nichts beantwortet. */
export const NichtFreigegeben: Story = {
    args: { formInstances: [buch] },
}
