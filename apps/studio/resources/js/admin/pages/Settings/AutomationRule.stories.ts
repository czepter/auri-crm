import type { Meta, StoryObj } from '@storybook/vue3-vite'
import AutomationRule from './AutomationRule.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Automatisierung',
  component: AutomationRule,
  parameters: { layout: 'fullscreen', url: '/einstellungen/automatisierungen/ar1' },
  args: {
    rule: {
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
      body: 'Hallo {{client.firstName}},\n\ndie Anzahlung über {{installment.amount}} steht noch offen.\n\nChristian',
      triggerType: 'date_offset',
      eventLabel: null,
      anchor: 'installment_due',
      offsetDays: 3,
      direction: 'after',
      projectType: null,
      action: 'mail_draft',
    },
    anchors: [
      { value: 'primary_shoot', label: 'Haupttermin' },
      { value: 'installment_due', label: 'Fälligkeit der Rate' },
    ],
    recipients: [
      { value: 'client', label: 'Kunde' },
      { value: 'crew', label: 'Crew' },
      { value: 'studio', label: 'Studio' },
    ],
    conditions: [
      { value: 'payment_open', label: 'Zahlung offen' },
    ],
    projectTypes: [
      { value: 'wedding', label: 'Hochzeit' },
      { value: 'portrait', label: 'Portrait' },
    ],
    actions: [
      { value: 'notice', label: 'Hinweis' },
      { value: 'task', label: 'Aufgabe' },
    ],
    mergeFields: [
      { path: 'client.firstName', sample: 'Lena' },
      { path: 'project.title', sample: 'Hochzeit Brinkmann' },
      { path: 'installment.amount', sample: '750,00 €' },
      { path: 'studio.name', sample: 'Atelier Nordlicht' },
    ],
    runs: [
      { id: 'run1', firedAt: '2026-09-11T09:00:00+02:00', anchorLabel: 'Hochzeit Brinkmann', status: 'sent', statusLabel: 'Gesendet' },
      { id: 'run2', firedAt: '2026-09-04T09:00:00+02:00', anchorLabel: 'Familie Ahlers', status: 'skipped', statusLabel: 'Übersprungen' },
      { id: 'run3', firedAt: '2026-08-28T09:00:00+02:00', anchorLabel: 'Ohlsen/Petersen', status: 'sent', statusLabel: 'Gesendet' },
    ],
  },
} satisfies Meta<typeof AutomationRule>

export default meta
type Story = StoryObj<typeof meta>

/** Eine laufende Regel mit Läufen der letzten Wochen. */
export const Aktiv: Story = {}

/** Eine gerade angelegte Regel: abgeschaltet, noch kein Lauf. */
export const NeuAngelegt: Story = {
  args: {
    rule: {
      ...meta.args.rule,
      name: '',
      subject: '',
      body: '',
      condition: null,
      conditionLabel: null,
      isActive: false,
      lastRunLabel: null,
      lastRunFailed: false,
    },
    runs: [],
  },
}
