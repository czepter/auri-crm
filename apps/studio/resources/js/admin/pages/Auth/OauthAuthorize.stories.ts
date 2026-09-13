import type { Meta, StoryObj } from '@storybook/vue3-vite'
import OauthAuthorize from './OauthAuthorize.vue'

const meta = {
  title: 'Bildschirme/Anmeldung/OAuth-Freigabe',
  component: OauthAuthorize,
  parameters: { layout: 'fullscreen', url: '/oauth/authorize' },
  args: {
    clientId: 'claude-code',
    clientName: 'Claude Code',
    redirectHost: 'claude.ai',
    redirectUri: 'https://claude.ai/api/mcp/auth_callback',
    codeChallenge: 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
    state: 'f1d2e3',
    scope: 'crm:read crm:write',
    scopes: [
      { value: 'crm:read', label: 'Anfragen und Projekte lesen' },
      { value: 'crm:write', label: 'Anfragen und Projekte ändern' },
    ],
  },
} satisfies Meta<typeof OauthAuthorize>

export default meta
type Story = StoryObj<typeof meta>

/** Die Freigabe mit zwei Bereichen und dem Ziel, an das zurückgeleitet wird. */
export const Freigabe: Story = {}
