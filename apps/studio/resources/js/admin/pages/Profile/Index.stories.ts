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
    workingDays: [1, 2, 3, 4, 5],
    pendingEmail: null,
    mfaConfirmed: true,
    sessions: [
      { id: 's1', ids: ['s1'], count: 1, device: 'Safari auf macOS', ip: '84.112.7.31', lastActiveAt: '2026-09-20T21:14:00+02:00', current: true },
      { id: 's2', ids: ['s2'], count: 1, device: 'Safari auf iOS', ip: '84.112.7.31', lastActiveAt: '2026-09-19T08:02:00+02:00', current: false },
    ],
    passkeys: [
      { id: 'p1', name: 'MacBook', createdAt: '2026-09-02T10:00:00+02:00', lastUsedAt: '2026-09-20T21:14:00+02:00' },
      { id: 'p2', name: 'iPhone', createdAt: '2026-09-18T08:30:00+02:00', lastUsedAt: null },
    ],
    recoveryCodesLeft: 8,
    digestFrequency: 'weekly',
    calendarFeedUrl: 'https://atelier-nordlicht.de/kalender/8f3c1a9e27b4/shootings.ics',
    carddav: null,
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
