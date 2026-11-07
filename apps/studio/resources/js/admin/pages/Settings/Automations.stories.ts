import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Automations from './Automations.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Automatisierungen',
  component: Automations,
  parameters: { layout: 'fullscreen', url: '/einstellungen/automatisierungen' },
  args: {
    timezone: 'Europe/Berlin',
    rules: [
      {
        id: 'ar1',
        name: 'Erinnerung an offene Anzahlung',
        triggerLabel: '3 Tage nach Fälligkeit der Rate',
        recipient: 'client',
        recipientLabel: 'Kunde',
        condition: 'payment_open',
        conditionLabel: 'Zahlung offen',
        isActive: true,
        lastRunLabel: 'gestern, 09:00',
        lastRunFailed: false,
        subject: 'Anzahlung für euren Termin',
        body: 'Hallo {{client.firstName}}, die Anzahlung steht noch offen.',
        triggerType: 'date_offset',
        eventLabel: null,
        anchor: 'installment_due',
        offsetDays: 3,
        direction: 'after',
        projectType: null,
        action: 'mail_draft',
        drafts: 0,
        group: 'Zahlung',
        order: 51503,
      },
      {
        id: 'ar2',
        name: 'Vorbesprechung eine Woche vorher',
        triggerLabel: '7 Tage vor dem Termin',
        recipient: 'client',
        recipientLabel: 'Kunde',
        condition: null,
        conditionLabel: null,
        isActive: true,
        lastRunLabel: '04.09.2026, 07:00',
        lastRunFailed: false,
        subject: 'Nächste Woche ist es soweit',
        body: 'Hallo {{client.firstName}}, wir telefonieren vorher kurz durch.',
        triggerType: 'date_offset',
        eventLabel: null,
        anchor: 'primary_shoot',
        offsetDays: 7,
        direction: 'before',
        projectType: null,
        action: 'mail_draft',
        drafts: 0,
        group: 'Shooting',
        order: 61493,
      },
      {
        id: 'ar3',
        name: 'Crew-Brief zum Termin',
        triggerLabel: 'Vertrag unterschrieben',
        recipient: 'crew',
        recipientLabel: 'Crew',
        condition: null,
        conditionLabel: null,
        isActive: false,
        lastRunLabel: null,
        lastRunFailed: false,
        subject: 'Neuer Termin im Kalender',
        body: 'Marie, {{project.title}} ist bestätigt.',
        triggerType: 'event',
        eventLabel: 'Vertrag unterschrieben',
        anchor: null,
        offsetDays: null,
        direction: null,
        projectType: null,
        action: 'notice',
        drafts: 0,
        group: 'Buchung',
        order: 42500,
      },
    ],
  },
} satisfies Meta<typeof Automations>

export default meta
type Story = StoryObj<typeof meta>

/** Zwei laufende Regeln, eine abgeschaltete. */
export const Eingerichtet: Story = {}

/** Ein Studio ohne Regeln. */
export const Leer: Story = { args: { rules: [] } }
