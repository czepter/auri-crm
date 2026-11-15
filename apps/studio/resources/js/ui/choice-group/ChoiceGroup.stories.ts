import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { ChoiceGroup } from '.'

const TYPEN = [
  { value: 'hochzeit', label: 'Hochzeit' },
  { value: 'portrait', label: 'Portrait' },
  { value: 'sonstige', label: 'Sonstige' },
]

const QUELLEN = [
  { value: 'empfehlung', label: 'Empfehlung' },
  { value: 'website', label: 'Website' },
  { value: 'instagram', label: 'Instagram' },
  { value: 'hochzeitsmesse', label: 'Hochzeitsmesse' },
]

/*
 * Drei bis fünf feste Werte als Reihe statt als Menü: sie sind getroffen,
 * bevor ein Dropdown offen ist. Ab sechs Werten oder wenn die Liste wachsen
 * darf, ist Select bzw. Combobox die Form.
 */
const meta = {
  title: 'Auswahl/ChoiceGroup',
  component: ChoiceGroup,
  args: { options: TYPEN, modelValue: 'hochzeit', label: 'Typ' },
  render: (args) => ({
    components: { ChoiceGroup },
    setup: () => ({ args }),
    template: '<ChoiceGroup v-bind="args" />',
  }),
} satisfies Meta<typeof ChoiceGroup>

export default meta
type Story = StoryObj<typeof meta>

export const Standard: Story = {}

/** Ohne Beschriftung, wenn die Reihe in einer Zeile neben anderem steht. */
export const OhneBeschriftung: Story = { args: { label: undefined } }

export const AlleVarianten: Story = {
  parameters: { layout: 'padded' },
  render: () => ({
    components: { ChoiceGroup },
    setup: () => ({ typen: TYPEN, quellen: QUELLEN }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 28px;">
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <ChoiceGroup :options="typen" model-value="portrait" label="Typ" />
          <code style="font-size: 11px; color: var(--muted);">drei Werte</code>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px;">
          <ChoiceGroup :options="quellen" model-value="empfehlung" label="Quelle" />
          <code style="font-size: 11px; color: var(--muted);">vier Werte</code>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px;">
          <ChoiceGroup :options="quellen" model-value="instagram" />
          <code style="font-size: 11px; color: var(--muted);">ohne Beschriftung</code>
        </div>
      </div>
    `,
  }),
}
