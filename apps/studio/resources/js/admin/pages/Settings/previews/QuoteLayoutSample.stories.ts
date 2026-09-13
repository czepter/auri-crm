import type { Meta, StoryObj } from '@storybook/vue3-vite'
import QuoteLayoutSample from './QuoteLayoutSample.vue'

const meta = {
  title: 'Dokumente/Angebot-Layouts',
  component: QuoteLayoutSample,
  parameters: { layout: 'fullscreen' },
} satisfies Meta<typeof QuoteLayoutSample>

export default meta
type Story = StoryObj<typeof meta>

export const Klassisch: Story = { args: { layout: 'classic' } }
export const Minimal: Story = { args: { layout: 'minimal' } }
export const Brief: Story = { args: { layout: 'letter' } }
export const Kopfband: Story = { args: { layout: 'header_band' } }
export const Struktur: Story = { args: { layout: 'structured' } }
