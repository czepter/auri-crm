import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Field } from '.'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'

/*
 * Beschriftung, Feld, Hinweis, Fehler — die vier Elemente, die vorher an 165
 * Stellen von Hand standen. Die id kommt aus dem Slot, nicht vom Aufrufer.
 */
const meta = {
  title: 'Formular/Field',
  component: Field,
  argTypes: {
    label: { control: 'text' },
    hint: { control: 'text' },
    error: { control: 'text' },
    wide: { control: 'boolean' },
    dense: { control: 'boolean' },
    id: { control: 'text' },
  },
  render: (args) => ({
    components: { Field, Input },
    setup: () => ({ args }),
    template: `
      <div style="width: 300px;">
        <Field v-bind="args" v-slot="{ id }">
          <Input :id="id" model-value="Hochzeit Rahn" />
        </Field>
      </div>
    `,
  }),
} satisfies Meta<typeof Field>

export default meta
type Story = StoryObj<typeof meta>

/** Ein Feld im Formular, Beschriftung darüber. */
export const Standard: Story = {
  args: { label: 'Projektname' },
}

/** Der Hinweis trägt, was die Beschriftung nicht sagen kann. */
export const MitHinweis: Story = {
  args: { label: 'Betrag (€)', hint: 'Netto, ohne Umsatzsteuer.' },
  render: (args) => ({
    components: { Field, Input },
    setup: () => ({ args }),
    template: `
      <div style="width: 300px;">
        <Field v-bind="args" v-slot="{ id }">
          <Input :id="id" model-value="4.260" />
        </Field>
      </div>
    `,
  }),
}

/** Nach der abgewiesenen Eingabe: der Grund steht unter dem Feld. */
export const MitFehler: Story = {
  args: { label: 'E-Mail', error: 'Keine gültige Adresse.' },
  render: (args) => ({
    components: { Field, Input },
    setup: () => ({ args }),
    template: `
      <div style="width: 300px;">
        <Field v-bind="args" v-slot="{ id }">
          <Input :id="id" model-value="lena.brandt@" aria-invalid="true" />
        </Field>
      </div>
    `,
  }),
}

/** Auf den Admin-Screens, wo die Beschriftung leichter wiegt als der Wert. */
export const Dicht: Story = {
  args: { label: 'Ort', dense: true },
  render: (args) => ({
    components: { Field, Input },
    setup: () => ({ args }),
    template: `
      <div style="width: 300px;">
        <Field v-bind="args" v-slot="{ id }">
          <Input :id="id" size="sm" model-value="Gut Basthorst" />
        </Field>
      </div>
    `,
  }),
}

/** Im zweispaltigen Raster das Feld, das über beide Spalten geht. */
export const ImRaster: Story = {
  parameters: { layout: 'padded' },
  render: () => ({
    components: { Field, Input, Textarea },
    template: `
      <div class="fgrid" style="width: 560px;">
        <Field :label="$t('leads.projectName')" v-slot="{ id }">
          <Input :id="id" model-value="Hochzeit Rahn" />
        </Field>
        <Field :label="$t('project.venueName')" v-slot="{ id }">
          <Input :id="id" model-value="Gut Basthorst" />
        </Field>
        <Field :label="$t('leads.contact')" v-slot="{ id }">
          <Input :id="id" model-value="Lena Brandt" />
        </Field>
        <Field :label="$t('payments.amount')" v-slot="{ id }">
          <Input :id="id" model-value="4.260" />
        </Field>
        <Field :label="$t('project.notes')" wide v-slot="{ id }">
          <Textarea :id="id" model-value="Trauung um 14 Uhr in der Kapelle." />
        </Field>
      </div>
    `,
  }),
}
