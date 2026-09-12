import type { Meta, StoryObj } from '@storybook/vue3-vite'
import ResetPassword from './ResetPassword.vue'

const meta = {
  title: 'Bildschirme/Anmeldung/Passwort neu setzen',
  component: ResetPassword,
  parameters: { layout: 'fullscreen', url: '/passwort/neu', page: { auth: { user: null, can: [] } } },
  args: { token: 'a1b2c3d4e5f6', email: 'christian@atelier-nordlicht.de' },
} satisfies Meta<typeof ResetPassword>

export default meta
type Story = StoryObj<typeof meta>

/** Aus dem Link heraus: die Adresse steht schon im Feld. */
export const AusDemLink: Story = {}

/** Ohne Adresse im Link muss sie mit eingetragen werden. */
export const OhneAdresse: Story = { args: { email: null } }
