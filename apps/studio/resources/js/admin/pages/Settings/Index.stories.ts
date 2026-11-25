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
  documentLayout: 'classic',
  remindersEnabled: true,
  clientRegister: 'formal',
  documentLetterheadHtml: null,
  documentFooterHtml: null,
  timezone: 'Europe/Berlin',
  workingDays: [1, 2, 3, 4, 5],
  mailFromName: 'Atelier Nordlicht',
  mailFromAddress: 'post@atelier-nordlicht.de',
  mailReplyTo: 'christian@atelier-nordlicht.de',
  mailSignature: 'Christian Zepter\nAtelier Nordlicht',
  mailBcc: null,
  mailClientsEnabled: true,
  mailDefaults: {},
  mailHeaderName: true,
  mailFooterLegal: true,
  mailFooterExtra: null,
}

const meta = {
  title: 'Bildschirme/Einstellungen/Studio',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/einstellungen/allgemein' },
  args: {
    pane: 'general',
    fontPairs,
    mailSenderFromEnv: { name: false, address: false },
    mailTemplates: [{ id: 'mt1', name: 'Angebot verschickt' }],
    settings,
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Marke, Anrede, Rechtsform, Anschrift, Steuerangaben und Zeitzone eines eingerichteten Studios. */
export const Allgemein: Story = {}

/** Absender, Antwortadresse und Signatur der ausgehenden Post. */
export const EMail: Story = {
  name: 'E-Mail',
  args: { pane: 'email' },
  parameters: { url: '/einstellungen/email' },
}

/** Bankverbindung, Zahlungsziel, Anzahlung und Mahnungen. */
export const Zahlung: Story = {
  args: { pane: 'payment' },
  parameters: { url: '/einstellungen/zahlung' },
}

/** Layout für Angebot, Rechnung, Gutschrift und Storno, dazu die Gültigkeit des Angebots. */
export const Layout: Story = {
  args: { pane: 'receipts' },
  parameters: { url: '/einstellungen/layout' },
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
    },
  },
}
