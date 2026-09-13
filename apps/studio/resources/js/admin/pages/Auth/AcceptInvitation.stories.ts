import type { Meta, StoryObj } from '@storybook/vue3-vite'
import AcceptInvitation from './AcceptInvitation.vue'

const meta = {
  title: 'Bildschirme/Anmeldung/Einladung annehmen',
  component: AcceptInvitation,
  parameters: { layout: 'fullscreen', url: '/einladung/a1b2c3', page: { auth: { user: null, can: [] } } },
  args: {
    name: 'Lena Ohlsen',
    email: 'lena.ohlsen@atelier-nordlicht.de',
    roleLabel: 'Fotograf',
    token: 'a1b2c3d4e5f6',
  },
} satisfies Meta<typeof AcceptInvitation>

export default meta
type Story = StoryObj<typeof meta>

/** Name und Rolle stehen fest, gesetzt wird nur noch das Passwort. */
export const Einladung: Story = {}
