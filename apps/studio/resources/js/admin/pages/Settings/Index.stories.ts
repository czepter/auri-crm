import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const fontPairs = [
  { value: 'hanken', label: 'Hanken', description: 'Eine Grotesk, Titel und Text gleich' },
  { value: 'fraunces', label: 'Fraunces', description: 'Kontrastreiche Serif, neutrale Grotesk' },
  { value: 'cormorant', label: 'Cormorant', description: 'Feine Serif, schmale Grotesk' },
  { value: 'grotesk', label: 'Space Grotesk', description: 'Technische Grotesk' },
]

const settings: App.Settings.Data.StudioSettingsData = {
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
  remindersEnabled: true,
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

const portal: App.Settings.Data.PortalSettingsData = {
  id: 'po1',
  maintenanceEnabled: false,
  maintenanceMessage: null,
  datenschutzUrl: 'https://atelier-nordlicht.de/datenschutz',
  agbUrl: null,
  impressumUrl: null,
  widerrufUrl: null,
  legalFamilies: { impressum: null, datenschutz: null, agb: null, widerruf: null },
  acceptQuotesEnabled: true,
  signContractsEnabled: true,
  paymentStatusEnabled: true,
  offersEnabled: true,
  galleryDownloadsEnabled: true,
  galleryListsEnabled: true,
  galleryCommentsEnabled: false,
  messagingMode: 'booking',
  clientRegister: 'formal',
  welcomeInquiry: null,
  welcomeActive: null,
  welcomeDone: null,
  dsgvoRecipient: 'datenschutz@atelier-nordlicht.de',
  dsgvoDeadlineDays: 30,
  contactName: null,
  contactAvatarUrl: null,
  contactPhone: null,
  contactEmail: null,
  contactResponseTime: null,
}

const meta = {
  title: 'Bildschirme/Einstellungen/Studio',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/einstellungen/marke' },
  args: {
    pane: 'brand',
    fontPairs,
    mailSenderFromEnv: { name: false, address: false },
    settings,
    portal,
    legalTemplates: [{ family: 'fam-widerruf-0000000000', name: 'Widerrufsbelehrung', updatedAt: '2026-06-12' }],
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Marke, Schrift und Akzentfarbe eines eingerichteten Studios. */
export const Marke: Story = {}

/** Rechtsform, Anschrift und Steuernummern — die Fläche hinter dem Briefkopf. */
export const Rechtliches: Story = {
  args: { pane: 'legal' },
  parameters: { url: '/einstellungen/rechtliche-einheit' },
}

/** Absender, Antwortadresse und Signatur der ausgehenden Post. */
export const EMail: Story = {
  name: 'E-Mail',
  args: { pane: 'email' },
  parameters: { url: '/einstellungen/email' },
}

/** Ein gerade angelegtes Studio: nur der Name steht, der Absender kommt aus der Umgebung. */
export const FrischAngelegt: Story = {
  args: {
    mailSenderFromEnv: { name: true, address: true },
    settings: {
      ...settings,
      legalName: null,
      legalForm: null,
      street: null,
      postcode: null,
      city: null,
      taxNumber: null,
      vatId: null,
      representative: null,
      website: null,
      accentColor: null,
      fontPair: null,
      bankAccountHolder: null,
      bankIban: null,
      bankBic: null,
      mailReplyTo: null,
      mailSignature: null,
      leadTypePresets: [],
      leadSourcePresets: [],
      leadChannelPresets: [],
    },
  },
}
