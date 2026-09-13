import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const meta = {
  title: 'Bildschirme/Benachrichtigungen',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/benachrichtigungen' },
  args: {
    notifications: [
      {
        id: 'n1',
        title: 'Neue Anfrage',
        body: 'Lena und Tobias Brinkmann — Hochzeit am 13.06.2027',
        url: '/anfragen/l1',
        readAt: null,
        createdAt: '2026-09-11T08:20:00+02:00',
      },
      {
        id: 'n2',
        title: 'Angebot angenommen',
        body: 'Familie Ahlers hat Angebot AN-2026-041 angenommen',
        url: '/projekte/p2',
        readAt: null,
        createdAt: '2026-09-09T17:05:00+02:00',
      },
      {
        id: 'n3',
        title: 'Rate bezahlt',
        body: 'Anzahlung 750,00 € — Hochzeit Ohlsen/Petersen',
        url: '/projekte/p3',
        readAt: '2026-09-08T09:00:00+02:00',
        createdAt: '2026-09-07T12:40:00+02:00',
      },
      {
        id: 'n4',
        title: 'Galerie kommentiert',
        body: 'Marie Ohlsen hat drei Bilder kommentiert',
        url: null,
        readAt: '2026-09-05T19:30:00+02:00',
        createdAt: '2026-09-05T18:12:00+02:00',
      },
    ],
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

/** Zwei ungelesene Zeilen über gelesenen. */
export const Ungelesen: Story = {}

/** Nichts angekommen. */
export const Leer: Story = { args: { notifications: [] } }
