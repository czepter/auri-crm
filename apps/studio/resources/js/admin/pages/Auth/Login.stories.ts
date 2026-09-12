import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Login from './Login.vue'

const meta = {
  title: 'Bildschirme/Anmeldung/Anmelden',
  component: Login,
  parameters: { layout: 'fullscreen', url: '/login', page: { auth: { user: null, can: [] } } },
  args: { devLogin: false },
} satisfies Meta<typeof Login>

export default meta
type Story = StoryObj<typeof meta>

/** Das leere Formular, wie es jeder Fremde zuerst sieht. */
export const Leer: Story = {}

/** Nur lokal: der Server reicht die Abkürzung durch, sonst steht sie nicht da. */
export const MitSchnellanmeldung: Story = { args: { devLogin: true } }
