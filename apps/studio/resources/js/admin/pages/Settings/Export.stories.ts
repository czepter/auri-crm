import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Export from './Export.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Export',
  component: Export,
  parameters: { layout: 'fullscreen', url: '/einstellungen/export' },
  args: {
    contacts: 121,
    projects: 38,
    archives: {
      daten: {
        id: 'exp1',
        status: 'ready',
        size: 48_300_000,
        createdAt: '2026-09-21T09:00:00+02:00',
        expiresAt: '2026-09-28T09:00:00+02:00',
        url: '/einstellungen/export/archiv/exp1',
      },
      galleries: [
        { id: 'gal1', title: 'Hochzeit Meier & Kowalski', size: 4_200_000_000, archive: null },
        { id: 'gal2', title: 'Familie Brandt', size: 812_000_000, archive: { id: 'exp2', status: 'building', size: null, createdAt: '2026-09-21T10:00:00+02:00', expiresAt: null, url: null } },
        { id: 'gal3', title: 'Businessportraits Nord', size: 1_540_000_000, archive: { id: 'exp3', status: 'failed', size: null, createdAt: '2026-09-20T10:00:00+02:00', expiresAt: null, url: null } },
      ],
    },
  },
} satisfies Meta<typeof Export>

export default meta
type Story = StoryObj<typeof meta>

export const Laufend: Story = {}
