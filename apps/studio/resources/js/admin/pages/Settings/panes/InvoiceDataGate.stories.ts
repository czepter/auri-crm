import type { Meta, StoryObj } from '@storybook/vue3-vite'
import InvoiceDataGate from './InvoiceDataGate.vue'
import { studioSettings as settings } from '../../__stories__/fixtures'

const meta = {
  title: 'Bildschirme/Einstellungen/Gate Rechnungsdaten',
  component: InvoiceDataGate,
  parameters: { layout: 'fullscreen', url: '/rechnungen/neu' },
  args: { open: true, settings, missing: ['street', 'postcode', 'city'] },
} satisfies Meta<typeof InvoiceDataGate>

export default meta
type Story = StoryObj<typeof meta>

/** Einzelunternehmen: kein Register, also auch kein Registerpaar. */
export const Einzelunternehmen: Story = {}

/** GmbH: Registernummer und Registergericht kommen dazu. */
export const Eingetragen: Story = {
  args: { settings: { ...settings, legalForm: 'GmbH', legalName: 'Atelier Nordlicht GmbH' } },
}
