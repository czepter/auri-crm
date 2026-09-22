import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const meta = {
  title: 'Bildschirme/Kalender/Schließzeiten',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/schliesszeiten' },
  args: {
    closures: [
      { id: 'c1', label: 'Weihnachtspause', startsOn: '2026-12-23', endsOn: '2027-01-04', message: 'Zurück am 5. Januar.' },
      { id: 'c2', label: 'Lofoten', startsOn: '2027-02-08', endsOn: '2027-02-22', message: null },
    ],
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Zwei eingetragene Schließzeiten. */
export const MitSchliesszeiten: Story = {}

/** Keine Schließzeit eingetragen. */
export const Leer: Story = { args: { closures: [] } }
