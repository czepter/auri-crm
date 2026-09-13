import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Create from './Create.vue'
import { channels, conflicts, presets, sources, types } from '../__stories__/fixtures'

const weddingFields = [
    {
        id: 'pf-guests',
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
        id: 'pf-ceremony',
        key: 'ceremony',
        label: 'Trauung',
        type: 'select',
        typeLabel: 'Auswahl',
        options: ['Standesamt', 'Kirchlich', 'Freie Trauung'],
        required: false,
        unit: null,
        allowNewOptions: true,
        isActive: true,
    },
]

const meta = {
    title: 'Bildschirme/Anfrage erfassen',
    component: Create,
    parameters: { layout: 'fullscreen', url: '/anfragen/neu' },
    args: {
        types,
        sources,
        channels,
        presets,
        fieldsByType: { wedding: weddingFields },
    },
} satisfies Meta<typeof Create>

export default meta
type Story = StoryObj<typeof meta>

/** Der Bildschirm beim Öffnen: nur die Suche, die Personenzeilen kommen erst nach ihr. */
export const Leer: Story = {}

/** Nach der Suche: Treffer über der Zeile, die einen neuen Kontakt anlegt. */
export const KontaktSuche: Story = {
    beforeEach: () => {
        const original = window.fetch
        const contacts: App.Crm.Data.ContactSuggestionData[] = [
            { id: 'c-1', name: 'Elisabeth Zeller', email: 'e.zeller@example.de', phone: null, projectCount: 1 },
        ]

        window.fetch = ((input: RequestInfo | URL) =>
            String(input).startsWith('/kontakte/suche')
                ? Promise.resolve(new Response(JSON.stringify({ contacts })))
                : original(input)) as typeof window.fetch

        return () => {
            window.fetch = original
        }
    },
    play: async ({ canvasElement }) => {
        const input = canvasElement.querySelector<HTMLInputElement>('input[placeholder="Suchen"]')

        if (!input) return

        input.value = 'Elisabeth Zepter'
        input.dispatchEvent(new Event('input', { bubbles: true }))
    },
}

/** Ein Wunschtermin, an dem schon etwas steht — die Meldung warnt, das Formular bleibt speicherbar. */
export const MitKollision: Story = {
    beforeEach: () => {
        const original = window.fetch

        window.fetch = ((input: RequestInfo | URL) =>
            String(input).startsWith('/verfuegbarkeit')
                ? Promise.resolve(new Response(JSON.stringify({ conflicts })))
                : original(input)) as typeof window.fetch

        return () => {
            window.fetch = original
        }
    },
    play: async ({ canvasElement }) => {
        const label = [...canvasElement.querySelectorAll('label')].find(
            (node) => node.textContent?.trim() === 'Haupttermin',
        )
        const input = canvasElement.querySelector<HTMLInputElement>(`#${label?.htmlFor}`)

        if (!input) return

        input.value = '12.06.2027'
        input.dispatchEvent(new Event('input', { bubbles: true }))
    },
}
