import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { InputGroup, InputGroupAddon, InputGroupInput } from '.'

/*
 * Rahmen, Hover und Fokus liegen auf der Gruppe, nicht auf dem Feld darin —
 * siehe InputGroup.vue. Deshalb stehen hier immer alle drei Bausteine.
 */
const meta = {
  title: 'Formular/InputGroup',
  component: InputGroup,
  render: (args) => ({
    components: { InputGroup, InputGroupAddon, InputGroupInput },
    setup: () => ({ args }),
    template: `
      <div style="width: 300px;">
        <InputGroup v-bind="args">
          <InputGroupAddon>https://</InputGroupAddon>
          <InputGroupInput model-value="gut-basthorst.de" />
        </InputGroup>
      </div>
    `,
  }),
} satisfies Meta<typeof InputGroup>

export default meta
type Story = StoryObj<typeof meta>

/** Der feste Teil vorne: ein Schema, das niemand tippen soll. */
export const Praefix: Story = {}

/** Die Einheit hinten, wo die Zahl allein mehrdeutig wäre. */
export const Suffix: Story = {
  render: (args) => ({
    components: { InputGroup, InputGroupAddon, InputGroupInput },
    setup: () => ({ args }),
    template: `
      <div style="width: 300px;">
        <InputGroup v-bind="args">
          <InputGroupInput model-value="4.260" />
          <InputGroupAddon align="inline-end">€</InputGroupAddon>
        </InputGroup>
      </div>
    `,
  }),
}

/** Wert steht fest, solange etwas anderes ihn hält. */
export const Deaktiviert: Story = {
  render: (args) => ({
    components: { InputGroup, InputGroupAddon, InputGroupInput },
    setup: () => ({ args }),
    template: `
      <div style="width: 300px;">
        <InputGroup v-bind="args">
          <InputGroupAddon>https://</InputGroupAddon>
          <InputGroupInput model-value="gut-basthorst.de" disabled />
        </InputGroup>
      </div>
    `,
  }),
}

/** Der Wert wurde abgelehnt; die Begründung trägt Field. */
export const Fehlerhaft: Story = {
  render: (args) => ({
    components: { InputGroup, InputGroupAddon, InputGroupInput },
    setup: () => ({ args }),
    template: `
      <div style="width: 300px;">
        <InputGroup v-bind="args">
          <InputGroupAddon>https://</InputGroupAddon>
          <InputGroupInput model-value="gut basthorst" aria-invalid="true" />
        </InputGroup>
      </div>
    `,
  }),
}

export const AlleZustaende: Story = {
  parameters: { layout: 'padded' },
  render: () => ({
    components: { InputGroup, InputGroupAddon, InputGroupInput },
    template: `
      <div style="display: flex; flex-direction: column; gap: 18px; width: 320px;">
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <InputGroup>
            <InputGroupAddon>https://</InputGroupAddon>
            <InputGroupInput model-value="gut-basthorst.de" />
          </InputGroup>
          <code style="font-size: 11px; color: var(--muted);">inline-start</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <InputGroup>
            <InputGroupInput model-value="4.260" />
            <InputGroupAddon align="inline-end">€</InputGroupAddon>
          </InputGroup>
          <code style="font-size: 11px; color: var(--muted);">inline-end</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <InputGroup>
            <InputGroupAddon>https://</InputGroupAddon>
            <InputGroupInput model-value="gut-basthorst.de" disabled />
          </InputGroup>
          <code style="font-size: 11px; color: var(--muted);">disabled</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <InputGroup>
            <InputGroupAddon>https://</InputGroupAddon>
            <InputGroupInput model-value="gut basthorst" aria-invalid="true" />
          </InputGroup>
          <code style="font-size: 11px; color: var(--muted);">aria-invalid</code>
        </div>
      </div>
    `,
  }),
}
