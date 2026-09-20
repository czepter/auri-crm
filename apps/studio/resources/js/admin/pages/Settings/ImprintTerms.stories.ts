import type { Meta, StoryObj } from '@storybook/vue3-vite'
import ImprintTerms from './ImprintTerms.vue'
import { settings } from './Portal.stories'

const meta = {
  title: 'Bildschirme/Einstellungen/Impressum & AGB',
  component: ImprintTerms,
  parameters: { layout: 'fullscreen', url: '/einstellungen/impressum-agb' },
  args: {
    settings,
    legalTemplates: [{ family: 'fam-widerruf-0000000000', name: 'Widerrufsbelehrung', updatedAt: '2026-06-12' }],
  },
} satisfies Meta<typeof ImprintTerms>

export default meta
type Story = StoryObj<typeof meta>

/** AGB als Link, Widerrufsbelehrung fehlt noch. */
export const Ausgefuellt: Story = {}

/** AGB und Widerrufsbelehrung liegen dem Angebot bei. */
export const Beigelegt: Story = {
  args: {
    settings: {
      ...settings,
      widerrufUrl: 'https://atelier-nordlicht.de/widerruf',
      agbAttach: true,
      widerrufAttach: true,
    },
  },
}
