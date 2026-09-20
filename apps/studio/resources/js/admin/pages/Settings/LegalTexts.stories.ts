import type { Meta, StoryObj } from '@storybook/vue3-vite'
import LegalTexts from './LegalTexts.vue'

const settings: App.Settings.Data.PortalSettingsData = {
  id: 'po1',
  maintenanceEnabled: false,
  maintenanceMessage: null,
  datenschutzUrl: 'https://atelier-nordlicht.de/datenschutz',
  agbUrl: 'https://atelier-nordlicht.de/agb',
  impressumUrl: null,
  widerrufUrl: null,
  legalFamilies: { impressum: null, datenschutz: null, agb: null, widerruf: null },
  acceptQuotesEnabled: true,
  signContractsEnabled: true,
  paymentStatusEnabled: true,
  offersEnabled: true,
  messagingMode: 'booking',
  clientRegister: 'formal',
  welcomeInquiry: 'Schön, dass ihr da seid.',
  welcomeActive: 'Hier liegen eure Termine, Unterlagen und Bilder.',
  welcomeDone: null,
  dsgvoRecipient: 'christian@atelier-nordlicht.de',
  dsgvoDeadlineDays: 30,
  contactPhone: '+49 40 4321987',
  contactEmail: 'post@atelier-nordlicht.de',
  contactResponseTime: 'werktags binnen 24 Stunden',
}

const meta = {
  title: 'Bildschirme/Einstellungen/Rechtstexte',
  component: LegalTexts,
  parameters: { layout: 'fullscreen', url: '/einstellungen/rechtstexte' },
  args: {
    settings,
    legalTemplates: [{ family: 'fam-widerruf-0000000000', name: 'Widerrufsbelehrung', updatedAt: '2026-06-12' }],
  },
} satisfies Meta<typeof LegalTexts>

export default meta
type Story = StoryObj<typeof meta>

/** Vier Texte, je Zeile Link oder Dokument. */
export const Vollstaendig: Story = {
  args: {
    settings: {
      ...settings,
      widerrufUrl: null,
      legalFamilies: { impressum: null, datenschutz: null, agb: null, widerruf: 'fam-widerruf-0000000000' },
    },
  },
}

/** Ohne Widerrufsbelehrung: das Angebot darf entstehen, aber nicht hinaus. */
export const Unvollstaendig: Story = {}
