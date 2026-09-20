import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const capabilities = [
  { value: 'manage_leads', label: 'Anfragen verwalten' },
  { value: 'view_all_projects', label: 'Alle Aufträge einsehen' },
  { value: 'book_project', label: 'Aufträge buchen' },
  { value: 'manage_staff', label: 'Team verwalten' },
]

const roles = [
  { value: 'owner', label: 'Inhaber', capabilities: capabilities.map((c) => c.value) },
  { value: 'second_shooter', label: 'Zweitfotograf', capabilities: ['manage_leads'] },
  { value: 'assistant', label: 'Assistenz', capabilities: ['manage_leads'] },
]

const christian = {
  id: 'u1',
  name: 'Christian Zepter',
  email: 'christian@atelier-nordlicht.de',
  role: 'owner',
  roleLabel: 'Inhaber',
  mfaConfirmed: true,
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
        role: 'second_shooter',
        roleLabel: 'Zweitfotograf',
        mfaConfirmed: false,
      },
    ],
    invitations: [
      {
        id: 'i1',
        email: 'jonas@atelier-nordlicht.de',
        name: 'Jonas Reincke',
        roleLabel: 'Assistenz',
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
