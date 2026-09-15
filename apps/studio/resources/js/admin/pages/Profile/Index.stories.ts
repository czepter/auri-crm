import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const events = {
  inquiryReceived: 'immediate',
  clientMessage: 'immediate',
  quoteAccepted: 'immediate',
  contractSigned: 'digest',
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
    user: {
      name: 'Christian Zepter',
      email: 'christian@atelier-nordlicht.de',
      phone: '+49 40 4321987',
      role: 'Inhaber',
    },
    pendingEmail: null,
    mfaConfirmed: true,
    recoveryCodesLeft: 8,
    digestFrequency: 'weekly',
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
  parameters: { url: '/profil?pane=sicherheit' },
}

/** Je Ereignis ein Kanal, dazu der Rhythmus der Zusammenfassung. */
export const Benachrichtigungen: Story = {
  parameters: { url: '/profil?pane=benachrichtigungen' },
}

/** Adresswechsel noch nicht bestätigt, zweiter Faktor nicht eingerichtet. */
export const NochNichtBestaetigt: Story = {
  parameters: { url: '/profil?pane=sicherheit' },
  args: {
    pendingEmail: 'christian@nordlicht.studio',
    mfaConfirmed: false,
    recoveryCodesLeft: 0,
  },
}
