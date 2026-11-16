import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { ref } from 'vue'
import { DatePicker } from '.'

/*
 * modelValue ist der ISO-Tag, den die API spricht: getippte Eingabe
 * (TT.MM.JJJJ) und Auswahl im Kalender landen beide auf demselben
 * YYYY-MM-DD. Der Kalender kennt kein defaultOpen — für die Popover-Ansicht
 * das Symbol im Feld anklicken.
 */
const meta = {
  title: 'Zeit/DatePicker',
  component: DatePicker,
  argTypes: {
  },
  args: { modelValue: '', size: 'default', disabled: false },
  render: ({ modelValue, ...rest }) => ({
    components: { DatePicker },
    setup: () => ({ rest, value: ref(modelValue ?? '') }),
    template: '<div style="width: 280px;"><DatePicker v-model="value" v-bind="rest" /></div>',
  }),
} satisfies Meta<typeof DatePicker>

export default meta
type Story = StoryObj<typeof meta>

export const Leer: Story = {}

/** Hochzeit Rahn, 13.09.2026. */
export const Gefuellt: Story = {
  name: 'Gefüllt',
  args: { modelValue: '2026-09-13' },
}

export const Deaktiviert: Story = { args: { modelValue: '2026-09-13', disabled: true } }

export const AlleZustaende: Story = {
  name: 'Alle Zustände',
  parameters: { layout: 'padded' },
  render: () => ({
    components: { DatePicker },
    setup: () => ({
      leer: ref(''),
      gefuellt: ref('2026-09-13'),
      deaktiviert: ref('2026-09-13'),
    }),
    template: `
      <div style="display: flex; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; flex-direction: column; gap: 6px; width: 260px;">
          <DatePicker v-model="leer" />
          <code style="font-size: 11px; color: var(--muted);">leer</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px; width: 260px;">
          <DatePicker v-model="gefuellt" />
          <code style="font-size: 11px; color: var(--muted);">default</code>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px; width: 260px;">
          <DatePicker v-model="deaktiviert" disabled />
          <code style="font-size: 11px; color: var(--muted);">disabled</code>
        </div>
      </div>
    `,
  }),
}
