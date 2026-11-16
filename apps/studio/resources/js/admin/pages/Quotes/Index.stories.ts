import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const row = (overrides: Partial<App.Quotes.Data.QuoteRowData> & Pick<App.Quotes.Data.QuoteRowData, 'id' | 'projectName'>): App.Quotes.Data.QuoteRowData => ({
  contact: null,
  number: null,
  status: 'draft',
  statusLabel: 'Entwurf',
  total: 0,
  sentAt: null,
  createdAt: '2026-11-14',
  ...overrides,
})

const meta = {
  title: 'Bildschirme/Angebote/Liste',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/angebote' },
  args: {
    quotes: [
      row({ id: 'q-1', projectName: 'Brandt, Familie', contact: 'Katrin Brandt', total: 255_850, createdAt: '2026-11-14' }),
      row({ id: 'q-2', projectName: 'Hochzeit Marie & Adrian', contact: 'Marie Fuhrmann', number: 'AN-2026-3', status: 'sent', statusLabel: 'Versendet', total: 295_000, sentAt: '2026-11-02', createdAt: '2026-10-30' }),
      row({ id: 'q-3', projectName: 'Rahn, Marie & Jonas', contact: 'Marie Rahn', number: 'AN-2026-2', status: 'accepted', statusLabel: 'Angenommen', total: 150_000, sentAt: '2026-08-20', createdAt: '2026-08-18' }),
      row({ id: 'q-4', projectName: 'Hochzeit Lisa + Lennart', contact: 'Elisabeth Zepter', number: 'AN-2026-1', status: 'superseded', statusLabel: 'Ersetzt', total: 210_000, sentAt: '2026-07-01', createdAt: '2026-06-28' }),
    ],
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

export const Liste: Story = {}

export const Leer: Story = { args: { quotes: [] } }
