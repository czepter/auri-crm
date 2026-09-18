import type { Meta, StoryObj } from '@storybook/vue3-vite'
import BusinessDataGate from './BusinessDataGate.vue'
import { studioSettings as settings } from '../../__stories__/fixtures'

const meta = {
  title: 'Bildschirme/Einstellungen/Gate Geschäftsdaten',
  component: BusinessDataGate,
  parameters: { layout: 'fullscreen', url: '/angebote/neu' },
  args: { open: true, settings },
} satisfies Meta<typeof BusinessDataGate>

export default meta
type Story = StoryObj<typeof meta>

/** Was vor dem ersten Angebot fehlt: Impressumsdaten und Umsatzsteuer. */
export const Offen: Story = {}

/** Regelbesteuerung: statt des Hinweistexts steht hier der Satz. */
export const Regelbesteuert: Story = {
  args: { settings: { ...settings, vatMode: 'standard' } },
}
