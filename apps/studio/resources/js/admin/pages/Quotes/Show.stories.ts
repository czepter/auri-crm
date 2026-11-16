import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Show from './Show.vue'
import { catalogItems, catalogPackages, contacts, leadQuote, quote } from '../__stories__/fixtures'

const meta = {
  title: 'Bildschirme/Angebote/Detail',
  component: Show,
  parameters: { layout: 'fullscreen', url: '/angebote/q-brandt' },
  args: {
    projectId: 'p-brandt',
    projectName: 'Hochzeit Brandt',
    projectIsBooked: false,
    canManageQuotes: true,
    canManagePaymentPlan: true,
    quotes: [leadQuote],
    packages: catalogPackages,
    catalogItems,
    contacts,
    canManageCatalog: true,
    paymentPlan: null,
    recipientName: 'Katrin Brandt',
    views: [],
    vatSettings: { mode: 'exempt', rate: 0, note: null },
  },
} satisfies Meta<typeof Show>

export default meta
type Story = StoryObj<typeof meta>

/** Versendet, noch nicht angenommen. */
export const Versendet: Story = {}

/** Angenommen, der Auftrag ist gebucht. */
export const Angenommen: Story = {
  args: { quotes: [quote], projectIsBooked: true },
}

/** Entwurf: gleicher Kopf wie auf der Anlegeseite. */
export const Entwurf: Story = {
  args: { quotes: [{ ...leadQuote, status: 'draft', statusLabel: 'Entwurf' }] },
}
