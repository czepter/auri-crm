import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Availability from './Availability.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Verfügbarkeit',
  component: Availability,
  parameters: { layout: 'fullscreen', url: '/einstellungen/verfuegbarkeit' },
  args: {
    workingDays: [1, 2, 3, 4, 5],
    closures: [
      { id: 'c1', label: 'Weihnachtspause', startsOn: '2026-12-23', endsOn: '2027-01-04', message: 'Zurück am 5. Januar.' },
      { id: 'c2', label: 'Lofoten', startsOn: '2027-02-08', endsOn: '2027-02-22', message: null },
    ],
  },
} satisfies Meta<typeof Availability>

export default meta
type Story = StoryObj<typeof meta>

/** Zwei eingetragene Schließzeiten. */
export const MitSchliesszeiten: Story = {}

/** Keine Schließzeit eingetragen. */
export const OhneSchliesszeiten: Story = { args: { closures: [] } }
