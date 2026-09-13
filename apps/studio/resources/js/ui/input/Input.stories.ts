import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Input } from '.'

/*
 * Die drei Größen sind der Portierung aus forms.css entnommen (siehe
 * index.ts); hier stehen sie nebeneinander, damit sichtbar bleibt, wofür
 * jede gedacht ist.
 */
const meta = {
  title: 'Formular/Input',
  component: Input,
  argTypes: {
    size: { control: 'inline-radio', options: ['default', 'sm', 'lead'] },
    modelValue: { control: 'text' },
    defaultValue: { control: 'text' },
  },
  args: { size: 'default', modelValue: 'Hochzeit Rahn' },
  render: (args) => ({
    components: { Input },
    setup: () => ({ args }),
    template: '<div style="width: 280px;"><Input v-bind="args" /></div>',
  }),
} satisfies Meta<typeof Input>

export default meta
type Story = StoryObj<typeof meta>

/** Das Feld im Formular: Projektname, Ort, Kontakt — alles, was getippt wird. */
export const Standard: Story = {}

/** Leer mit Platzhalter, wo ein Beispiel schneller trägt als eine Erklärung. */
export const Platzhalter: Story = {
  args: { modelValue: '' },
  render: (args) => ({
    components: { Input },
    setup: () => ({ args }),
    template: '<div style="width: 280px;"><Input v-bind="args" placeholder="Gut Basthorst" /></div>',
  }),
}

/** Das Feld, das in einer Zeile steht statt in einem Formular. */
export const Klein: Story = { args: { size: 'sm', modelValue: '4.260 €' } }

/** Der Haupttermin, an dem die ganze Erfassung hängt. */
export const Haupttermin: Story = { args: { size: 'lead', modelValue: '2026-07-18' } }

/** Wert steht fest, solange etwas anderes ihn hält. */
export const Deaktiviert: Story = {
  render: (args) => ({
    components: { Input },
    setup: () => ({ args }),
    template: '<div style="width: 280px;"><Input v-bind="args" disabled /></div>',
  }),
}

/** Der Wert wurde abgelehnt; die Begründung trägt Field. */
export const Fehlerhaft: Story = {
  args: { modelValue: 'Lena Brandt' },
  render: (args) => ({
    components: { Input },
    setup: () => ({ args }),
    template: '<div style="width: 280px;"><Input v-bind="args" aria-invalid="true" /></div>',
  }),
}

export const AlleGroessen: Story = {
  parameters: { layout: 'padded' },
  render: () => ({
    components: { Input },
    setup: () => ({
      groessen: [
        { size: 'default', value: 'Hochzeit Rahn' },
        { size: 'sm', value: '4.260 €' },
        { size: 'lead', value: '2026-07-18' },
      ] as const,
    }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 18px; width: 320px;">
        <div v-for="g in groessen" :key="g.size" style="display: flex; flex-direction: column; gap: 6px;">
          <Input :size="g.size" :model-value="g.value" />
          <code style="font-size: 11px; color: var(--muted);">{{ g.size }}</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <Input model-value="Gut Basthorst" disabled />
          <code style="font-size: 11px; color: var(--muted);">disabled</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <Input model-value="Lena Brandt" aria-invalid="true" />
          <code style="font-size: 11px; color: var(--muted);">aria-invalid</code>
        </div>
      </div>
    `,
  }),
}
