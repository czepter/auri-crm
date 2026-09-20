import type { Meta, StoryObj } from '@storybook/vue3-vite'
import ApiTokens from './ApiTokens.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/API-Token',
  component: ApiTokens,
  parameters: { layout: 'fullscreen', url: '/einstellungen/api-token' },
  args: {
    tokens: [
      { id: 'tok_01', name: 'n8n', scopes: ['manage_leads', 'manage_contacts'], createdAt: '2026-09-01T10:00:00+02:00' },
    ],
    capabilities: [
      { value: 'manage_leads', label: 'Anfragen verwalten' },
      { value: 'manage_contacts', label: 'Kontakte verwalten' },
      { value: 'view_all_projects', label: 'Alle Aufträge einsehen' },
    ],
    mcpUrl: 'https://api.atelier-nordlicht.de/mcp',
  },
} satisfies Meta<typeof ApiTokens>

export default meta
type Story = StoryObj<typeof meta>

/** Ein aktiver Token für n8n. */
export const Aktiv: Story = {}

/** Ohne Token. */
export const Leer: Story = {
  args: { tokens: [] },
}
