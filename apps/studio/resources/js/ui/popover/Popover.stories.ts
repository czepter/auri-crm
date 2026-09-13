import type { Meta, StoryObj } from '@storybook/vue3-vite'
import type { DateValue } from 'reka-ui'
import { parseDate } from '@internationalized/date'
import { CalendarDays } from 'lucide-vue-next'
import { ref } from 'vue'
import { Calendar } from '@/ui/calendar'
import { inputVariants } from '@/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '.'

const parts = { Popover, PopoverContent, PopoverTrigger, Calendar, CalendarDays }

const meta = {
  title: 'Überlagerung/Popover',
  component: Popover,
  args: { defaultOpen: true, modal: false },
  parameters: { layout: 'centered' },
} satisfies Meta<typeof Popover>

export default meta
type Story = StoryObj<typeof meta>

/** Kontext zum Feld daneben, nicht zur Seite: der Kalender des DatePickers, die einzige Stelle, an der ein Popover steht. */
export const Kalender: Story = {
  render: (args) => ({
    components: parts,
    setup: () => ({
      args,
      field: inputVariants(),
      date: ref<DateValue>(parseDate('2026-06-14')),
    }),
    template: `
      <div class="relative w-[220px]" style="height: 380px;">
        <input :class="[field, 'pr-9']" value="14.06.2026" inputmode="numeric" readonly>

        <Popover v-bind="args">
          <PopoverTrigger
            type="button"
            class="absolute inset-y-0 right-0 flex w-9 cursor-pointer items-center justify-center text-muted-foreground outline-none hover:text-foreground"
          >
            <CalendarDays class="size-4" />
            <span class="sr-only">Kalender</span>
          </PopoverTrigger>
          <PopoverContent align="end" class="p-0">
            <Calendar
              v-model="date"
              locale="de-DE"
              :week-starts-on="1"
              weekday-format="short"
              calendar-label="Kalender"
            />
          </PopoverContent>
        </Popover>
      </div>
    `,
  }),
}
