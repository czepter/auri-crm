import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const capabilities = [
  { value: 'manage_leads', label: 'Anfragen verwalten' },
  { value: 'view_all_projects', label: 'Alle Aufträge einsehen' },
  { value: 'book_project', label: 'Aufträge buchen' },
  { value: 'manage_staff', label: 'Team verwalten' },
]

const roles = [
  { value: 'admin', label: 'Admin', capabilities: capabilities.map((c) => c.value) },
  { value: 'manager', label: 'Manager', capabilities: ['manage_leads', 'view_all_projects'] },
  { value: 'contributor', label: 'Mitwirkende', capabilities: ['manage_leads'] },
  { value: 'reader', label: 'Leser', capabilities: ['view_all_projects'] },
]

const christian = {
  id: 'u1',
  name: 'Christian Zepter',
  email: 'christian@atelier-nordlicht.de',
  role: 'admin',
  roleLabel: 'Admin',
  mfaConfirmed: true,
  transferable: false,
}

const meta = {
  title: 'Bildschirme/Team',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/team' },
  args: {
    staff: [
      christian,
      {
        id: 'u2',
        name: 'Marie Ohlsen',
        email: 'marie@atelier-nordlicht.de',
        role: 'contributor',
        roleLabel: 'Mitwirkende',
        mfaConfirmed: false,
        transferable: true,
      },
    ],
    invitations: [
      {
        id: 'i1',
        email: 'jonas@atelier-nordlicht.de',
        name: 'Jonas Reincke',
        roleLabel: 'Mitwirkende',
        expiresOn: '19.09.2026',
      },
    ],
    roles,
    capabilities,
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Zwei Fotografen und eine offene Einladung. */
export const Besetzt: Story = {}

/** Das Studio besteht aus einer Person: eine Zeile, keine Einladung. */
export const EinePerson: Story = {
  parameters: { page: { soloStudio: true } },
  args: { staff: [christian], invitations: [] },
}
