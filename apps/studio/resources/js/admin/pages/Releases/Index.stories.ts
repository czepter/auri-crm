import type { Meta, StoryObj } from '@storybook/vue3-vite'
import type { FeedItem } from '@/admin/lib/releases'
import Index from './Index.vue'

const releases: FeedItem[] = [
  {
    version: '2026.9.2',
    date: '2026-09-10',
    headline: 'Abwesenheiten im Kalender',
    body: ['Urlaub und freie Tage stehen jetzt neben den Terminen, statt nur auf der eigenen Seite.'],
    image: null,
    sections: [
      { key: 'new', label: 'Neu', count: 2 },
      { key: 'changed', label: 'Geändert', count: 5 },
      { key: 'fixed', label: 'Behoben', count: 3 },
    ],
  },
  {
    version: '2026.9.1',
    date: '2026-09-02',
    headline: 'Nummernkreise je Dokumentart',
    body: ['Angebot, Vertrag und Rechnung zählen getrennt, mit eigenem Präfix.'],
    image: null,
    sections: [
      { key: 'new', label: 'Neu', count: 1 },
      { key: 'fixed', label: 'Behoben', count: 4 },
    ],
  },
  {
    version: '2026.8.3',
    date: '2026-08-19',
    headline: null,
    body: [],
    image: null,
    sections: [{ key: 'fixed', label: 'Behoben', count: 2 }],
  },
]

const meta = {
  title: 'Bildschirme/Neuigkeiten',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/neuigkeiten' },
  args: { releases, installed: '2026.9.1', mediaBase: null, checkedAt: '2026-09-10T08:00:00+02:00' },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Die laufende Version in der Mitte, darüber eine verfügbare. */
export const Feed: Story = {}
