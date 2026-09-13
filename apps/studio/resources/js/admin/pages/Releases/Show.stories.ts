import type { Meta, StoryObj } from '@storybook/vue3-vite'
import type { ReleaseDetail } from '@/admin/lib/releases'
import Show from './Show.vue'

const release: ReleaseDetail = {
  version: '2026.9.2',
  tag: 'v2026.9.2',
  date: '2026-09-10',
  headline: 'Abwesenheiten im Kalender',
  body: ['Urlaub und freie Tage stehen jetzt neben den Terminen, statt nur auf der eigenen Seite.'],
  image: null,
  sections: [
    {
      key: 'new',
      label: 'Neu',
      count: 2,
      items: [
        {
          type: 'feat',
          title: 'Abwesenheiten im Kalender',
          description: 'Die Monatsansicht zeigt freie Tage der Crew unter den Terminen.',
          commit: '4f2ac91',
        },
        {
          type: 'feat',
          title: 'Termin-Feed abonnieren',
          description: null,
          commit: 'b81d305',
        },
      ],
    },
    {
      key: 'fixed',
      label: 'Behoben',
      count: 1,
      items: [
        {
          type: 'fix',
          title: 'Doppelter Status nach Verschieben eines Termins',
          description: 'Ein verschobener Termin trug den alten Status ein zweites Mal.',
          commit: '0c7e448',
        },
      ],
    },
  ],
}

const meta = {
  title: 'Bildschirme/Neuigkeiten/Release',
  component: Show,
  parameters: { layout: 'fullscreen', url: '/neuigkeiten/2026.9.2' },
  args: { release, installed: '2026.9.1', mediaBase: null },
} satisfies Meta<typeof Show>

export default meta
type Story = StoryObj<typeof meta>

/** Eine Version, die noch nicht läuft, mit ihren Änderungen nach Gruppe. */
export const Verfuegbar: Story = {}

/** Dieselbe Version als die laufende. */
export const Installiert: Story = { args: { installed: '2026.9.2' } }
