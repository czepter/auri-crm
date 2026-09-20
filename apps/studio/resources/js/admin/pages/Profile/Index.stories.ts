import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const events = {
  inquiryReceived: 'immediate',
  clientMessage: 'immediate',
  dsgvoRequested: 'immediate',
  quoteAccepted: 'immediate',
  questionnaireSubmitted: 'digest',
  installmentPaid: 'digest',
  installmentDueSoon: 'immediate',
  assetCommented: 'off',
  assetFavorited: 'off',
  tasksDue: 'briefing',
  leadStalled: 'briefing',
  installmentOverdue: 'immediate',
} as const

const meta = {
  title: 'Bildschirme/Profil',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/profil' },
  args: {
    pane: 'konto',
    user: {
      name: 'Christian Zepter',
      email: 'christian@atelier-nordlicht.de',
      phone: '+49 40 4321987',
      role: 'Inhaber',
    },
    pendingEmail: null,
    mfaConfirmed: true,
    sessions: [
      { id: 's1', device: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleW…', ip: '84.112.7.31', lastActiveAt: '2026-09-20T21:14:00+02:00', current: true },
      { id: 's2', device: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X…', ip: '84.112.7.31', lastActiveAt: '2026-09-19T08:02:00+02:00', current: false },
    ],
    recoveryCodesLeft: 8,
    digestFrequency: 'weekly',
    calendarFeedUrl: 'https://atelier-nordlicht.de/kalender/8f3c1a9e27b4/shootings.ics',
    events: { ...events },
    briefable: {
      tasksDue: true,
      leadStalled: true,
      installmentOverdue: true,
    },
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Name, Telefon und Rolle des angemeldeten Fotografen. */
export const Konto: Story = {}

/** Zweiter Faktor bestätigt, acht Wiederherstellungscodes übrig. */
export const Sicherheit: Story = {
  args: { pane: 'sicherheit' },
  parameters: { url: '/profil/sicherheit' },
}

/** Je Ereignis ein Kanal, dazu der Rhythmus der Zusammenfassung. */
export const Benachrichtigungen: Story = {
  args: { pane: 'benachrichtigungen' },
  parameters: { url: '/profil/benachrichtigungen' },
}

/** Die Abo-Adresse des Termin-Feeds. */
export const Kalender: Story = {
  args: { pane: 'kalender' },
  parameters: { url: '/profil/kalender' },
}

/** Adresswechsel noch nicht bestätigt, zweiter Faktor nicht eingerichtet. */
export const NochNichtBestaetigt: Story = {
  parameters: { url: '/profil/sicherheit' },
  args: {
    pane: 'sicherheit',
    pendingEmail: 'christian@nordlicht.studio',
    mfaConfirmed: false,
    recoveryCodesLeft: 0,
  },
}
