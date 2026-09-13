import type { Meta, StoryObj } from '@storybook/vue3-vite'
import MfaEnroll from './MfaEnroll.vue'

const secret = 'JBSWY3DPEHPK3PXP'

const meta = {
  title: 'Bildschirme/Anmeldung/Zwei-Faktor einrichten',
  component: MfaEnroll,
  parameters: { layout: 'fullscreen', url: '/auth/mfa/einrichten', page: { auth: { user: null, can: [] } } },
  args: {
    secret,
    otpauthUri: `otpauth://totp/Atelier%20Nordlicht:christian@atelier-nordlicht.de?secret=${secret}&issuer=Atelier%20Nordlicht`,
  },
} satisfies Meta<typeof MfaEnroll>

export default meta
type Story = StoryObj<typeof meta>

/** QR-Code, aufklappbares Geheimnis und die erste Bestätigung. */
export const Einrichtung: Story = {}
