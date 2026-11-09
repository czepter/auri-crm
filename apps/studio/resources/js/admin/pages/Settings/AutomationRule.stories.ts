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
      body: 'Hallo {{ contact.name }},\n\ndie Anzahlung über {{ invoice.gross }} steht noch offen.\n\nChristian',
      triggerType: 'date_offset',
      eventLabel: null,
      anchor: 'installment_due',
      offsetDays: 3,
      direction: 'after',
      projectType: null,
      action: 'mail_draft',
      drafts: 1,
      group: 'Zahlung',
      order: 51503,
    },
    eventClause: null,
    anchors: [
      { value: 'primary_shoot', label: 'Haupttermin', noun: 'dem Haupttermin', at: 'am Tag des Haupttermins', canBefore: true },
      { value: 'delivered', label: 'Auslieferung', noun: 'der Auslieferung', at: 'am Tag der Auslieferung', canBefore: false },
      { value: 'installment_due', label: 'Fälligkeit der Rate', noun: 'Fälligkeit der Rate', at: 'am Fälligkeitstag der Rate', canBefore: true },
    ],
    recipients: [
      { value: 'client', label: 'Kunde' },
      { value: 'crew', label: 'Crew' },
      { value: 'studio', label: 'Studio' },
    ],
    conditions: [
      { value: 'payment_open', label: 'Zahlung offen', clause: 'die Rate noch offen ist' },
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
      { path: 'contact.name', sample: 'Lena Meier', group: 'Kontakt', label: 'Name', needsPayment: false },
      { path: 'project.name', sample: 'Hochzeit Brinkmann', group: 'Projekt', label: 'Projektname', needsPayment: false },
      { path: 'invoice.gross', sample: '750,00 €', group: 'Rechnung', label: 'Brutto', needsPayment: true },
      { path: 'studio.name', sample: 'Atelier Nordlicht', group: 'Studio', label: 'Name', needsPayment: false },
    ],
    testRecipient: 'christian@example.test',
    previewProjects: [
      { value: '', label: 'Beispiel' },
      { value: 'p1', label: 'Hochzeit Brinkmann' },
    ],
    runs: [
      { id: 'run1', firedAt: '2026-09-11T09:00:00+02:00', anchorLabel: 'Hochzeit Brinkmann', status: 'sent', statusLabel: 'Gesendet', draftUrl: null },
      { id: 'run2', firedAt: '2026-09-04T09:00:00+02:00', anchorLabel: 'Familie Ahlers', status: 'awaiting_approval', statusLabel: 'Entwurf', draftUrl: '/posteingang/entwuerfe/run2/senden' },
      { id: 'run3', firedAt: '2026-08-28T09:00:00+02:00', anchorLabel: 'Ohlsen/Petersen', status: 'sent', statusLabel: 'Gesendet', draftUrl: null },
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
