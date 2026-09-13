import type { Meta, StoryObj } from '@storybook/vue3-vite'
import type { DateValue } from 'reka-ui'
import { CalendarDate } from '@internationalized/date'
import { ref } from 'vue'
import { Calendar } from '.'

/* Der Wert ist ein DateValue aus @internationalized/date, kein ISO-String. */
const hochzeitRahn = new CalendarDate(2026, 9, 13)

const meta = {
  title: 'Zeit/Calendar',
  component: Calendar,
  args: {
    modelValue: hochzeitRahn,
    defaultPlaceholder: hochzeitRahn,
    locale: 'de-DE',
    weekStartsOn: 1,
    weekdayFormat: 'short',
    calendarLabel: 'Kalender',
  },
  render: ({ modelValue, ...rest }) => ({
    components: { Calendar },
    setup: () => ({ rest, value: ref(modelValue as DateValue) }),
    template: '<Calendar v-model="value" v-bind="rest" class="rounded-md border border-line-hover" />',
  }),
} satisfies Meta<typeof Calendar>

export default meta
type Story = StoryObj<typeof meta>

export const Standard: Story = {}

/**
 * Gewählt der 13.09.2026, heute daneben. Der 21. ist gesperrt, der 26. belegt,
 * die Randwochen zeigen die Nachbarmonate.
 */
export const Zellzustaende: Story = {
  name: 'Zellzustände',
  args: {
    fixedWeeks: true,
    isDateDisabled: (date: DateValue) => date.month === 9 && date.day === 21,
    isDateUnavailable: (date: DateValue) => date.month === 9 && date.day === 26,
  },
}
