import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Index from './Index.vue'

const row = (overrides: Partial<App.Contracts.Data.ContractRowData> & Pick<App.Contracts.Data.ContractRowData, 'id' | 'projectName'>): App.Contracts.Data.ContractRowData => ({
  projectId: 'p-1',
  contact: null,
  version: 1,
  state: 'valid',
  status: 'rendered',
  createdOn: '2026-11-01',
  sentOn: null,
  signedOn: null,
  deadlineOn: null,
  hasDocument: true,
  ...overrides,
})

const meta = {
  title: 'Bildschirme/Verträge/Liste',
  component: Index,
  parameters: { layout: 'fullscreen', url: '/vertraege' },
  args: {
    contracts: [
      row({ id: 'c-1', projectName: 'Brandt, Familie', contact: 'Katrin Brandt', state: 'draft', status: 'pending', createdOn: '2026-11-14', hasDocument: false }),
      row({ id: 'c-2', projectName: 'Hochzeit Marie & Adrian', contact: 'Marie Fuhrmann', sentOn: '2026-11-02', deadlineOn: '2026-11-30' }),
      row({ id: 'c-3', projectName: 'Rahn, Marie & Jonas', contact: 'Marie Rahn', sentOn: '2026-08-20', signedOn: '2026-08-24' }),
      row({ id: 'c-4', projectName: 'Hochzeit Lisa + Lennart', contact: 'Elisabeth Zepter', state: 'superseded', sentOn: '2026-07-01', signedOn: '2026-07-03' }),
    ],
  },
} satisfies Meta<typeof Index>

export default meta
type Story = StoryObj<typeof meta>

export const Liste: Story = {}

export const Leer: Story = { args: { contracts: [] } }
