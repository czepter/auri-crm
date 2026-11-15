import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Import from './Import.vue'

const SOCIAL_PLATFORMS = [
  { value: 'instagram', label: 'Instagram' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'tiktok', label: 'TikTok' },
  { value: 'pinterest', label: 'Pinterest' },
  { value: 'linkedin', label: 'LinkedIn' },
  { value: 'website', label: 'Website' },
]

const PREVIEW = {
  headers: ['Vorname', 'Nachname', 'E-Mail', 'Telefon', 'Kundennummer'],
  samples: ['Lena', 'Brandt', 'lena.brandt@posteo.de', '0176 2244881', 'K-1042'],
  mapping: ['first_name', 'last_name', 'email', 'phone', ''],
  rows: [
    { line: 2, name: 'Lena Brandt', email: 'lena.brandt@posteo.de', status: 'create', reason: null },
    { line: 3, name: 'Tom Brandt', email: 'tom@brandt-tischlerei.de', status: 'create', reason: null },
    { line: 4, name: 'Kerstin Voß', email: 'k.voss@nordwerk.de', status: 'existing', reason: null },
    { line: 5, name: 'Tom Brandt', email: 'tom@brandt-tischlerei.de', status: 'repeated', reason: null },
    { line: 6, name: 'Jan Ahrens', email: 'jan.ahrens', status: 'invalid', reason: 'Das Feld E-Mail muss eine gültige E-Mail-Adresse sein.' },
  ],
}

const meta = {
  title: 'Bildschirme/Kontakte/Import',
  component: Import,
  parameters: { layout: 'fullscreen', url: '/kontakte/import' },
  args: { socialPlatforms: SOCIAL_PLATFORMS },
} satisfies Meta<typeof Import>

export default meta
type Story = StoryObj<typeof meta>

export const Leer: Story = {}

/** Nach dem Hochladen: Spalten mit vorbelegter Zuordnung, darunter die Vorschau jeder Zeile. */
export const Vorschau: Story = {
  beforeEach: () => {
    const original = window.fetch

    window.fetch = ((input: RequestInfo | URL) =>
      String(input).startsWith('/kontakte/import/vorschau')
        ? Promise.resolve(new Response(JSON.stringify(PREVIEW)))
        : original(input)) as typeof window.fetch

    return () => {
      window.fetch = original
    }
  },
  play: async ({ canvasElement }) => {
    const input = canvasElement.querySelector<HTMLInputElement>('input[type="file"]')

    if (!input) return

    const transfer = new DataTransfer()
    transfer.items.add(new File(['Vorname;Nachname'], 'kunden.csv', { type: 'text/csv' }))
    input.files = transfer.files
    input.dispatchEvent(new Event('change', { bubbles: true }))
  },
}
