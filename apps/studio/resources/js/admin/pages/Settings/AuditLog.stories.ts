import type { Meta, StoryObj } from '@storybook/vue3-vite'
import AuditLog from './AuditLog.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Protokoll',
  component: AuditLog,
  parameters: { layout: 'fullscreen', url: '/einstellungen/protokoll' },
  args: {
    entries: [
      { id: 'a1', at: '2026-09-20T21:14:00+02:00', event: 'quote.sent', actor: 'Christian Zepter', label: 'Angebot gesendet', detail: [], target: { name: 'Hochzeit Lena & Jonas', href: '/auftraege/p1' } },
      { id: 'a2', at: '2026-09-20T18:02:00+02:00', event: 'contact.created', actor: 'Nina Kowalski', label: 'Kontakt angelegt', detail: [], target: { name: 'Lena Brandt', href: '/kontakte/c1' } },
      { id: 'a3', at: '2026-09-19T09:40:00+02:00', event: 'login.failed', actor: null, label: 'Anmeldung fehlgeschlagen', detail: [], target: null },
    ],
    nextCursor: null,
    filters: { ereignis: '', person: '', von: '', bis: '', anmeldungen: false },
    events: [
      { value: 'quote.sent', label: 'Angebot gesendet' },
      { value: 'contact.created', label: 'Kontakt angelegt' },
    ],
    people: [
      { value: 'u1', label: 'Christian Zepter' },
      { value: 'u2', label: 'Nina Kowalski' },
    ],
  },
} satisfies Meta<typeof AuditLog>

export default meta
type Story = StoryObj<typeof meta>

export const Liste: Story = {}

export const Weitere: Story = { args: { nextCursor: 'eyJjcmVhdGVkX2F0IjpudWxsfQ' } }
