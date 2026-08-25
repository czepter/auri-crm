<script setup lang="ts">
import type { DateValue } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { InputVariants } from "@/ui/input"
import { parseDate } from "@internationalized/date"
import { CalendarDays } from "lucide-vue-next"
import { computed, ref, watch } from "vue"
import { cn } from "@/lib/utils"
import { Calendar } from "@/ui/calendar"
import { inputVariants } from "@/ui/input"
import { Popover, PopoverContent, PopoverTrigger } from "@/ui/popover"
import { toGerman, toIso } from "./parse"

const props = defineProps<{
  id?: string
  modelValue?: string
  size?: InputVariants["size"]
  required?: boolean
  disabled?: boolean
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", value: string): void
  // Fired once a value is settled — the field loses focus, or a date is
  // picked from the calendar — carrying the raw text rather than the
  // parsed result: update:modelValue alone cannot tell a deliberate clear
  // ("") from a half-typed date ("31.12.20", still mid-entry), since
  // toIso() collapses both to the same null. A caller that only wants to
  // react to a settled value, not every keystroke, listens here instead.
  //
  // Not a native `blur` forwarded through attrs fallthrough: the root
  // element is the wrapping <div>, and `blur` does not bubble to it even
  // if a caller wrote @blur on the component — this emit is what makes
  // that actually reach a listener.
  (e: "blur", text: string): void
}>()

const open = ref(false)
const text = ref(props.modelValue ? toGerman(props.modelValue) : "")

watch(() => props.modelValue, (value) => {
  if ((value ?? "") === (toIso(text.value) ?? ""))
    return
  text.value = value ? toGerman(value) : ""
})

const calendarValue = computed({
  get: (): DateValue | undefined => {
    try {
      return props.modelValue ? parseDate(props.modelValue) : undefined
    }
    catch {
      return undefined
    }
  },
  set: (value: DateValue | undefined) => {
    if (!value)
      return
    // A calendar pick is always a complete date — never mid-entry — so it
    // settles the field the same way leaving it does, whether or not the
    // text input itself ever held focus to blur from.
    const iso = value.toString()
    emits("update:modelValue", iso)
    emits("blur", iso)
    open.value = false
  },
})

function onInput(event: Event) {
  text.value = (event.target as HTMLInputElement).value
  emits("update:modelValue", toIso(text.value) ?? "")
}

function onBlur() {
  emits("blur", text.value)
  text.value = props.modelValue ? toGerman(props.modelValue) : ""
}
</script>

<template>
  <div class="relative" data-slot="date-picker">
    <input
      :id="id"
      :value="text"
      :required="required"
      :disabled="disabled"
      inputmode="numeric"
      autocomplete="off"
      placeholder="TT.MM.JJJJ"
      :class="cn(inputVariants({ size }), 'pr-9', props.class)"
      @input="onInput"
      @blur="onBlur"
    >

    <Popover v-model:open="open">
      <PopoverTrigger
        type="button"
        :disabled="disabled"
        class="absolute inset-y-0 right-0 flex w-9 cursor-pointer items-center justify-center text-muted-foreground outline-none hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
      >
        <CalendarDays class="size-4" />
        <span class="sr-only">{{ $t('common.calendar') }}</span>
      </PopoverTrigger>
      <PopoverContent align="end" class="p-0">
        <Calendar
          v-model="calendarValue"
          :default-placeholder="calendarValue"
          locale="de-DE"
          :week-starts-on="1"
          weekday-format="short"
          :calendar-label="$t('common.calendar')"
          initial-focus
        />
      </PopoverContent>
    </Popover>
  </div>
</template>
