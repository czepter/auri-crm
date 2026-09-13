import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { Textarea } from '.'

/*
 * Dasselbe Feld wie Input, nur mit mehr Raum — die Klassen kommen aus
 * inputVariants, damit beide nicht auseinanderlaufen.
 */
const meta = {
  title: 'Formular/Textarea',
  component: Textarea,
  argTypes: {
    modelValue: { control: 'text' },
    defaultValue: { control: 'text' },
  },
  args: {
    modelValue: 'Trauung um 14 Uhr in der Kapelle, Empfang auf der Wiese hinter dem Gutshaus.',
  },
  render: (args) => ({
    components: { Textarea },
    setup: () => ({ args }),
    template: '<div style="width: 340px;"><Textarea v-bind="args" /></div>',
  }),
} satisfies Meta<typeof Textarea>

export default meta
type Story = StoryObj<typeof meta>

/** Alles, was länger ist als eine Zeile: Notiz, Absprache, Beschreibung. */
export const Standard: Story = {}

/** Leer, mit dem Platzhalter aus dem Katalog. */
export const Platzhalter: Story = {
  args: { modelValue: '' },
  render: (args) => ({
    components: { Textarea },
    setup: () => ({ args }),
    template: '<div style="width: 340px;"><Textarea v-bind="args" :placeholder="$t(\'project.notePlaceholder\')" /></div>',
  }),
}

/** Text bleibt lesbar, während ihn hier gerade niemand ändern darf. */
export const Deaktiviert: Story = {
  render: (args) => ({
    components: { Textarea },
    setup: () => ({ args }),
    template: '<div style="width: 340px;"><Textarea v-bind="args" disabled /></div>',
  }),
}

/** Der Text wurde abgelehnt; die Begründung trägt Field. */
export const Fehlerhaft: Story = {
  render: (args) => ({
    components: { Textarea },
    setup: () => ({ args }),
    template: '<div style="width: 340px;"><Textarea v-bind="args" aria-invalid="true" /></div>',
  }),
}

export const AlleZustaende: Story = {
  parameters: { layout: 'padded' },
  render: () => ({
    components: { Textarea },
    setup: () => ({ text: 'Trauung um 14 Uhr in der Kapelle, Empfang auf der Wiese hinter dem Gutshaus.' }),
    template: `
      <div style="display: flex; flex-wrap: wrap; gap: 20px;">
        <div style="display: flex; flex-direction: column; gap: 6px; width: 280px;">
          <Textarea :model-value="text" />
          <code style="font-size: 11px; color: var(--muted);">gefüllt</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px; width: 280px;">
          <Textarea model-value="" :placeholder="$t('project.notePlaceholder')" />
          <code style="font-size: 11px; color: var(--muted);">leer</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px; width: 280px;">
          <Textarea :model-value="text" disabled />
          <code style="font-size: 11px; color: var(--muted);">disabled</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px; width: 280px;">
          <Textarea :model-value="text" aria-invalid="true" />
          <code style="font-size: 11px; color: var(--muted);">aria-invalid</code>
        </div>
      </div>
    `,
  }),
}
