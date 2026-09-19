import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Portal from './Portal.vue'

const branding: App.Settings.Data.StudioSettingsData = {
  id: 'st1',
  displayName: 'Atelier Nordlicht',
  legalName: 'Atelier Nordlicht — Christian Zepter',
  legalForm: 'Einzelunternehmen',
  street: 'Bei den Mühren 7',
  postcode: '20457',
  city: 'Hamburg',
  country: 'DE',
  taxNumber: '43/123/45678',
  vatId: 'DE812345678',
  registerNumber: null,
  registerCourt: null,
  representative: 'Christian Zepter',
  website: 'https://atelier-nordlicht.de',
  accentColor: '#2F4858',
  fontPair: 'fraunces',
  logoUrl: null,
  showcaseUrls: [],
  bankAccountHolder: 'Christian Zepter',
  bankIban: 'DE02120300000000202051',
  bankBic: 'BYLADEM1001',
  vatMode: 'regular',
  vatRate: 1900,
  vatNote: null,
  quoteValidityDays: 14,
  paymentTermDays: 14,
  depositPercentage: 30,
  widerrufIncluded: true,
  widerrufText: null,
  quoteLayout: 'classic',
  clientRegister: 'formal',
  documentLetterheadHtml: null,
  documentFooterHtml: null,
  timezone: 'Europe/Berlin',
  locale: 'de',
  workingDays: [1, 2, 3, 4, 5],
  mailFromName: 'Atelier Nordlicht',
  mailFromAddress: 'post@atelier-nordlicht.de',
  mailReplyTo: 'christian@atelier-nordlicht.de',
  mailSignature: 'Christian Zepter\nAtelier Nordlicht',
  mailBcc: null,
  mailClientsEnabled: true,
  leadTypePresets: ['Hochzeit', 'Paarshooting', 'Familie'],
  leadSourcePresets: ['Empfehlung', 'Instagram', 'Website'],
  leadChannelPresets: ['E-Mail', 'Telefon', 'Kontaktformular'],
}

const settings: App.Settings.Data.PortalSettingsData = {
  id: 'po1',
  maintenanceEnabled: false,
  maintenanceMessage: null,
  welcomeText: 'Hier liegen eure Termine, Unterlagen und Bilder.',
  datenschutzUrl: 'https://atelier-nordlicht.de/datenschutz',
  agbUrl: 'https://atelier-nordlicht.de/agb',
  impressumUrl: null,
  widerrufUrl: null,
  legalFamilies: { impressum: null, datenschutz: null, agb: null, widerruf: null },
  acceptQuotesEnabled: true,
  paymentStatusEnabled: true,
  offersEnabled: true,
  messagingMode: 'booking',
  clientRegister: 'formal',
  welcomeInquiry: 'Schön, dass ihr da seid.',
  welcomeMany: null,
  dsgvoRecipient: 'christian@atelier-nordlicht.de',
  dsgvoDeadlineDays: 30,
  contactPhone: '+49 40 4321987',
  contactEmail: 'post@atelier-nordlicht.de',
  contactResponseTime: 'werktags binnen 24 Stunden',
}

const meta = {
  title: 'Bildschirme/Einstellungen/Portal',
  component: Portal,
  parameters: { layout: 'fullscreen', url: '/einstellungen/portal' },
  args: {
    legalTemplates: [{ family: 'fam-widerruf-0000000000', name: 'Widerrufsbelehrung', updatedAt: '2026-06-12' }], settings, branding },
} satisfies Meta<typeof Portal>

export default meta
type Story = StoryObj<typeof meta>

/** Portal offen: Angebote, Verträge und Zahlungsstand freigeschaltet. */
export const Offen: Story = {}

/** Wartung an, Nachrichten aus — das Portal zeigt nur die Meldung. */
export const Wartung: Story = {
  args: {
    settings: {
      ...settings,
      maintenanceEnabled: true,
      maintenanceMessage: 'Wir sind Montag wieder erreichbar.',
      messagingMode: 'off',
    },
  },
}

/** Der Abschnitt Rechtliches: vier Texte, je Zeile Link oder Dokument. */
export const Rechtliches: Story = {
  parameters: { url: '/einstellungen/portal?pane=privacy' },
  args: {
    settings: {
      ...settings,
      widerrufUrl: null,
      legalFamilies: { impressum: null, datenschutz: null, agb: null, widerruf: 'fam-widerruf-0000000000' },
    },
  },
}

/** Ohne Widerrufsbelehrung: das Angebot darf entstehen, aber nicht hinaus. */
export const RechtlichesUnvollstaendig: Story = {
  parameters: { url: '/einstellungen/portal?pane=privacy' },
  args: {
    settings: {
      ...settings,
      widerrufUrl: null,
      legalFamilies: { impressum: null, datenschutz: null, agb: null, widerruf: null },
    },
  },
}
