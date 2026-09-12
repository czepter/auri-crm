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
  argTypes: {
    variant: { control: 'inline-radio', options: ['default', 'segmented'] },
  },
  args: { variant: 'default', options: TYPEN, modelValue: 'hochzeit', label: 'Typ' },
  render: (args) => ({
    components: { ChoiceGroup },
    setup: () => ({ args }),
    template: '<ChoiceGroup v-bind="args" />',
  }),
} satisfies Meta<typeof ChoiceGroup>

export default meta
type Story = StoryObj<typeof meta>

/** Pillen: die freistehende Reihe, etwa als Filter über einer Liste. */
export const Standard: Story = {}

/** Die Schiene: ein Umschalter, bei dem immer genau eine Ansicht gilt. */
export const Segmented: Story = { args: { variant: 'segmented' } }

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
          <ChoiceGroup :options="typen" model-value="hochzeit" label="Typ" />
          <code style="font-size: 11px; color: var(--muted);">default</code>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px;">
          <ChoiceGroup :options="typen" model-value="portrait" label="Typ" variant="segmented" />
          <code style="font-size: 11px; color: var(--muted);">segmented</code>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px;">
          <ChoiceGroup :options="quellen" model-value="empfehlung" label="Quelle" variant="segmented" />
          <code style="font-size: 11px; color: var(--muted);">segmented, vier Werte</code>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px;">
          <ChoiceGroup :options="quellen" model-value="instagram" />
          <code style="font-size: 11px; color: var(--muted);">default, ohne Beschriftung</code>
        </div>
      </div>
    `,
  }),
}
