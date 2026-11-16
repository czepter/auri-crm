import type { Meta, StoryObj } from '@storybook/vue3-vite'
import {
  Combobox,
  ComboboxAnchor,
  ComboboxContent,
  ComboboxGroup,
  ComboboxInput,
  ComboboxItem,
  ComboboxLabel,
} from '.'

const parts = { Combobox, ComboboxAnchor, ComboboxContent, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxLabel }

const meta = {
  title: 'Auswahl/Combobox',
  component: Combobox,
  args: { disabled: false },
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <div style="width: 280px;">
        <Combobox v-bind="args">
          <ComboboxAnchor>
            <ComboboxInput placeholder="Projekttyp" />
          </ComboboxAnchor>
          <ComboboxContent>
            <ComboboxItem value="Hochzeit">Hochzeit</ComboboxItem>
            <ComboboxItem value="Portrait">Portrait</ComboboxItem>
            <ComboboxItem value="Sonstige">Sonstige</ComboboxItem>
          </ComboboxContent>
        </Combobox>
      </div>
    `,
  }),
} satisfies Meta<typeof Combobox>

export default meta
type Story = StoryObj<typeof meta>

/** Eine Auswahl, die wachsen darf: tippen filtert, ein neuer Wert bleibt möglich. */
export const Geschlossen: Story = {}

export const Geoeffnet: Story = { args: { defaultOpen: true } }

export const Deaktiviert: Story = { args: { disabled: true, defaultValue: 'Hochzeit' } }

/** Überschriften, wenn die Vorschläge aus mehreren Herkünften kommen. */
export const MitGruppen: Story = {
  args: { defaultOpen: true },
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <div style="width: 280px;">
        <Combobox v-bind="args">
          <ComboboxAnchor>
            <ComboboxInput placeholder="Quelle" />
          </ComboboxAnchor>
          <ComboboxContent>
            <ComboboxGroup>
              <ComboboxLabel>Persönlich</ComboboxLabel>
              <ComboboxItem value="Empfehlung">Empfehlung</ComboboxItem>
              <ComboboxItem value="Hochzeitsmesse">Hochzeitsmesse</ComboboxItem>
            </ComboboxGroup>
            <ComboboxGroup>
              <ComboboxLabel>Online</ComboboxLabel>
              <ComboboxItem value="Website">Website</ComboboxItem>
              <ComboboxItem value="Instagram">Instagram</ComboboxItem>
            </ComboboxGroup>
          </ComboboxContent>
        </Combobox>
      </div>
    `,
  }),
}

/** Ein getippter Wert, den es noch nicht gibt, steht als letzte Zeile zum Anlegen bereit. */
export const MitAnlegen: Story = {
  args: { defaultOpen: true },
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <div style="width: 280px;">
        <Combobox v-bind="args" ignore-filter>
          <ComboboxAnchor>
            <ComboboxInput model-value="Hoch" />
          </ComboboxAnchor>
          <ComboboxContent>
            <ComboboxItem value="Hochzeit">Hochzeit</ComboboxItem>
            <ComboboxItem value="Hoch" create>„Hoch“ anlegen</ComboboxItem>
          </ComboboxContent>
        </Combobox>
      </div>
    `,
  }),
}

export const AlleZustaende: Story = {
  parameters: { layout: 'padded' },
  render: () => ({
    components: parts,
    template: `
      <div style="display: flex; flex-wrap: wrap; gap: 24px;">
        <div style="display: flex; flex-direction: column; gap: 6px; width: 240px;">
          <Combobox>
            <ComboboxAnchor>
              <ComboboxInput placeholder="Projekttyp" />
            </ComboboxAnchor>
          </Combobox>
          <code style="font-size: 11px; color: var(--muted);">leer</code>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px; width: 240px;">
          <Combobox default-value="Portrait">
            <ComboboxAnchor>
              <ComboboxInput />
            </ComboboxAnchor>
          </Combobox>
          <code style="font-size: 11px; color: var(--muted);">gewählt</code>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px; width: 240px;">
          <Combobox disabled default-value="Hochzeit">
            <ComboboxAnchor>
              <ComboboxInput />
            </ComboboxAnchor>
          </Combobox>
          <code style="font-size: 11px; color: var(--muted);">disabled</code>
        </div>
      </div>
    `,
  }),
}
