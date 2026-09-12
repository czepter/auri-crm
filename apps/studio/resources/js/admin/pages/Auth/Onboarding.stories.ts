import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Onboarding from './Onboarding.vue'

const settings: App.Settings.Data.StudioSettingsData = {
  id: 'st-1',
  displayName: 'Atelier Nordlicht',
  legalName: 'Christian Zepter',
  legalForm: 'Einzelunternehmen',
  street: 'Hoheluftchaussee 42',
  postcode: '20253',
  city: 'Hamburg',
  country: 'DE',
  taxNumber: null,
  vatId: null,
  registerNumber: null,
  registerCourt: null,
  representative: 'Christian Zepter',
  website: 'https://atelier-nordlicht.de',
  accentColor: '#1f3a5f',
  fontPair: 'fraunces',
  logoUrl: null,
  showcaseUrls: [],
  bankAccountHolder: null,
  bankIban: null,
  bankBic: null,
  vatMode: 'kleinunternehmer',
  vatRate: 1900,
  vatNote: null,
  quoteValidityDays: 14,
  paymentTermDays: 14,
  depositPercentage: 30,
  widerrufIncluded: true,
  widerrufText: null,
  documentLetterheadHtml: null,
  documentFooterHtml: null,
  timezone: 'Europe/Berlin',
  locale: 'de',
  workingDays: [1, 2, 3, 4, 5],
  mailFromName: 'Atelier Nordlicht',
  mailFromAddress: 'post@atelier-nordlicht.de',
  mailReplyTo: null,
  mailSignature: null,
  mailBcc: null,
  leadTypePresets: [],
  leadSourcePresets: [],
  leadChannelPresets: [],
}

const meta = {
  title: 'Bildschirme/Anmeldung/Einrichtung Studio',
  component: Onboarding,
  parameters: { layout: 'fullscreen', url: '/einrichtung/studio' },
  args: { settings, emailVerified: true, hasLiveVerification: true },
} satisfies Meta<typeof Onboarding>

export default meta
type Story = StoryObj<typeof meta>

/** Schritt 1: was das Studio auf Angebot und Rechnung schreibt. */
export const SchrittProfil: Story = {}

/** Derselbe Schritt, solange die Adresse noch auf ihren Code wartet. */
export const SchrittProfilUnbestaetigt: Story = { args: { emailVerified: false } }
