import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Label } from '@/ui/label'
import { RadioGroup, RadioGroupItem } from '.'

const parts = { RadioGroup, RadioGroupItem, Label }

const meta = {
  title: 'Auswahl/RadioGroup',
  component: RadioGroup,
  args: { defaultValue: 'hochzeit', disabled: false },
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <RadioGroup v-bind="args" name="typ">
        <div style="display: flex; align-items: center; gap: 10px;">
          <RadioGroupItem id="typ-hochzeit" value="hochzeit" />
          <Label variant="inline" for="typ-hochzeit">Hochzeit</Label>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
          <RadioGroupItem id="typ-portrait" value="portrait" />
          <Label variant="inline" for="typ-portrait">Portrait</Label>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
          <RadioGroupItem id="typ-sonstige" value="sonstige" />
          <Label variant="inline" for="typ-sonstige">Sonstige</Label>
        </div>
      </RadioGroup>
    `,
  }),
} satisfies Meta<typeof RadioGroup>

export default meta
type Story = StoryObj<typeof meta>

/** Die gestapelte Form: eine Entscheidung mit Tragweite, alle Optionen lesbar untereinander. */
export const Standard: Story = {}

export const Deaktiviert: Story = { args: { disabled: true } }

/** Einzelne Option gesperrt, etwa wenn ein Wert für dieses Studio nicht gilt. */
export const EinzelnDeaktiviert: Story = {
  render: () => ({
    components: parts,
    template: `
      <RadioGroup default-value="empfehlung" name="quelle">
        <div style="display: flex; align-items: center; gap: 10px;">
          <RadioGroupItem id="q-empfehlung" value="empfehlung" />
          <Label variant="inline" for="q-empfehlung">Empfehlung</Label>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
          <RadioGroupItem id="q-messe" value="hochzeitsmesse" disabled />
          <Label variant="inline" for="q-messe">Hochzeitsmesse</Label>
        </div>
      </RadioGroup>
    `,
  }),
}

export const AlleZustaende: Story = {
  parameters: { layout: 'padded' },
  render: () => ({
    components: parts,
    template: `
      <div style="display: flex; flex-wrap: wrap; gap: 40px;">
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <RadioGroup default-value="website" name="a">
            <div style="display: flex; align-items: center; gap: 10px;">
              <RadioGroupItem id="a-website" value="website" />
              <Label variant="inline" for="a-website">Website</Label>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
              <RadioGroupItem id="a-instagram" value="instagram" />
              <Label variant="inline" for="a-instagram">Instagram</Label>
            </div>
          </RadioGroup>
          <code style="font-size: 11px; color: var(--muted);">standard</code>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
          <RadioGroup default-value="website" name="b" disabled>
            <div style="display: flex; align-items: center; gap: 10px;">
              <RadioGroupItem id="b-website" value="website" />
              <Label variant="inline" for="b-website">Website</Label>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
              <RadioGroupItem id="b-instagram" value="instagram" />
              <Label variant="inline" for="b-instagram">Instagram</Label>
            </div>
          </RadioGroup>
          <code style="font-size: 11px; color: var(--muted);">disabled</code>
        </div>
      </div>
    `,
  }),
}
