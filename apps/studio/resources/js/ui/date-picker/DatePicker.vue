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
    emits("update:modelValue", value.toString())
    open.value = false
  },
})

function onInput(event: Event) {
  text.value = (event.target as HTMLInputElement).value
  emits("update:modelValue", toIso(text.value) ?? "")
}

function onBlur() {
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
