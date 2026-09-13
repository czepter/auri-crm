import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Availability from './Availability.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Verfügbarkeit',
  component: Availability,
  parameters: { layout: 'fullscreen', url: '/einstellungen/verfuegbarkeit' },
  args: {
    defaultShootDurationMinutes: 480,
    closures: [
      { id: 'c1', label: 'Weihnachtspause', startsOn: '2026-12-23', endsOn: '2027-01-04' },
      { id: 'c2', label: 'Lofoten', startsOn: '2027-02-08', endsOn: '2027-02-22' },
    ],
  },
} satisfies Meta<typeof Availability>

export default meta
type Story = StoryObj<typeof meta>

/** Regeldauer eines Termins und zwei eingetragene Schließzeiten. */
export const MitSchliesszeiten: Story = {}

/** Nur die Regeldauer, keine Schließzeit eingetragen. */
export const OhneSchliesszeiten: Story = { args: { closures: [] } }
