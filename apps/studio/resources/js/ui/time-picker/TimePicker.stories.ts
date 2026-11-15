import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { ref } from 'vue'
import { TimePicker } from '.'

/*
 * modelValue ist "HH:MM", wie es die Validierung (date_format:H:i) erwartet.
 * Stunde und Minute sind eigene Segmente: Pfeiltasten zählen, Tippen schreibt.
 */
const meta = {
  title: 'Zeit/TimePicker',
  component: TimePicker,
  argTypes: {
  },
  args: { modelValue: '', size: 'default', disabled: false },
  render: ({ modelValue, ...rest }) => ({
    components: { TimePicker },
    setup: () => ({ rest, value: ref(modelValue ?? '') }),
    template: '<div style="width: 140px;"><TimePicker v-model="value" v-bind="rest" /></div>',
  }),
} satisfies Meta<typeof TimePicker>

export default meta
type Story = StoryObj<typeof meta>

export const Leer: Story = {}

/** Beginn 10:00. */
export const Gefuellt: Story = {
  name: 'Gefüllt',
  args: { modelValue: '10:00' },
}

/** Ende 23:00. */
export const Deaktiviert: Story = { args: { modelValue: '23:00', disabled: true } }
