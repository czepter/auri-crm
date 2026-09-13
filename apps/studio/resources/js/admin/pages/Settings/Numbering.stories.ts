import type { Meta, StoryObj } from '@storybook/vue3-vite'
import Numbering from './Numbering.vue'

const meta = {
  title: 'Bildschirme/Einstellungen/Nummernkreise',
  component: Numbering,
  parameters: { layout: 'fullscreen', url: '/einstellungen/nummernkreise' },
  args: {
    ranges: [
      {
        id: 'nr1',
        documentType: 'quote',
        documentTypeLabel: 'Angebot',
        prefix: 'AN-',
        resetRule: 'yearly',
        startingCounter: 1,
        canSetStartingCounter: false,
        nextNumberPreview: 'AN-2026-042',
      },
      {
        id: 'nr2',
        documentType: 'contract',
        documentTypeLabel: 'Vertrag',
        prefix: 'VT-',
        resetRule: 'yearly',
        startingCounter: 1,
        canSetStartingCounter: false,
        nextNumberPreview: 'VT-2026-019',
      },
      {
        id: 'nr3',
        documentType: 'invoice',
        documentTypeLabel: 'Rechnung',
        prefix: 'RE-',
        resetRule: 'continuous',
        startingCounter: 1000,
        canSetStartingCounter: true,
        nextNumberPreview: 'RE-1000',
      },
    ],
  },
} satisfies Meta<typeof Numbering>

export default meta
type Story = StoryObj<typeof meta>

/** Angebot und Vertrag laufen bereits, die Rechnung ist noch frei einstellbar. */
export const Laufend: Story = {}
