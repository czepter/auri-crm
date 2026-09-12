import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { ROLE_SUGGESTIONS } from '@/admin/lib/roles'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '.'

const parts = { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue }

const meta = {
  title: 'Auswahl/Select',
  component: Select,
  args: { disabled: false },
  render: (args) => ({
    components: parts,
    setup: () => ({ args }),
    template: `
      <div style="width: 280px;">
        <Select v-bind="args">
          <SelectTrigger><SelectValue placeholder="Projekttyp" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="hochzeit">Hochzeit</SelectItem>
            <SelectItem value="portrait">Portrait</SelectItem>
            <SelectItem value="sonstige">Sonstige</SelectItem>
          </SelectContent>
        </Select>
      </div>
    `,
  }),
} satisfies Meta<typeof Select>

export default meta
type Story = StoryObj<typeof meta>

/** Die geschlossene Form: ab sechs Werten, oder wenn die Liste wachsen darf. */
export const Geschlossen: Story = {}

/** Aufgeklappt, wie beim Klick auf den Trigger. */
export const Geoeffnet: Story = { args: { defaultOpen: true } }

export const Deaktiviert: Story = { args: { disabled: true, defaultValue: 'hochzeit' } }

/** Mit Überschriften, wenn die Werte aus mehreren Herkünften kommen. */
export const MitGruppen: Story = {
  render: () => ({
    components: parts,
    template: `
      <div style="width: 280px;">
        <Select default-value="empfehlung">
          <SelectTrigger><SelectValue placeholder="Quelle" /></SelectTrigger>
          <SelectContent>
            <SelectGroup>
              <SelectLabel>Persönlich</SelectLabel>
              <SelectItem value="empfehlung">Empfehlung</SelectItem>
              <SelectItem value="hochzeitsmesse">Hochzeitsmesse</SelectItem>
            </SelectGroup>
            <SelectGroup>
              <SelectLabel>Online</SelectLabel>
              <SelectItem value="website">Website</SelectItem>
              <SelectItem value="instagram">Instagram</SelectItem>
            </SelectGroup>
          </SelectContent>
        </Select>
      </div>
    `,
  }),
}

/**
 * Freitext statt Auswahl, wenn die bekannten Werte nur Vorschläge sind:
 * Kontaktrollen, Typ und Quelle — „Trauredner" steht in keiner Aufzählung.
 */
export const FreitextMitVorschlaegen: Story = {
  render: () => ({
    components: { Field, Input },
    setup: () => ({ rollen: ROLE_SUGGESTIONS }),
    template: `
      <div style="width: 280px;">
        <Field v-slot="{ id }" dense label="Rolle">
          <Input :id="id" type="text" list="rollen" />
        </Field>
        <datalist id="rollen">
          <option v-for="value in rollen" :key="value" :value="value" />
        </datalist>
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
          <Select>
            <SelectTrigger><SelectValue placeholder="Projekttyp" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="hochzeit">Hochzeit</SelectItem>
              <SelectItem value="portrait">Portrait</SelectItem>
              <SelectItem value="sonstige">Sonstige</SelectItem>
            </SelectContent>
          </Select>
          <code style="font-size: 11px; color: var(--muted);">leer</code>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px; width: 240px;">
          <Select default-value="portrait">
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="hochzeit">Hochzeit</SelectItem>
              <SelectItem value="portrait">Portrait</SelectItem>
              <SelectItem value="sonstige">Sonstige</SelectItem>
            </SelectContent>
          </Select>
          <code style="font-size: 11px; color: var(--muted);">gewählt</code>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px; width: 240px;">
          <Select disabled default-value="hochzeit">
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="hochzeit">Hochzeit</SelectItem>
            </SelectContent>
          </Select>
          <code style="font-size: 11px; color: var(--muted);">disabled</code>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px; width: 240px;">
          <Select>
            <SelectTrigger size="sm"><SelectValue placeholder="Quelle" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="empfehlung">Empfehlung</SelectItem>
              <SelectItem value="website">Website</SelectItem>
              <SelectItem value="instagram">Instagram</SelectItem>
              <SelectItem value="hochzeitsmesse">Hochzeitsmesse</SelectItem>
            </SelectContent>
          </Select>
          <code style="font-size: 11px; color: var(--muted);">size="sm"</code>
        </div>
      </div>
    `,
  }),
}
