<script setup lang="ts">
import type { TimeValue } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { InputVariants } from "@/ui/input"
import { Clock } from "lucide-vue-next"
import { TimeFieldInput, TimeFieldRoot } from "reka-ui"
import { computed } from "vue"
import { cn } from "@/lib/utils"
import { inputVariants } from "@/ui/input"
import { toClock, toTime } from "./parse"

const props = defineProps<{
  id?: string
  modelValue?: string | null
  size?: InputVariants["size"]
  required?: boolean
  disabled?: boolean
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", value: string): void
}>()

const value = computed({
  get: () => toTime(props.modelValue),
  set: (time: TimeValue | undefined) => emits("update:modelValue", toClock(time)),
})
</script>

<template>
  <!-- Stunde und Minute sind je ein eigenes Segment: Pfeiltasten zählen, die
       Tastatur schreibt, und der Wert bleibt "HH:MM", wie ihn die Validierung
       (date_format:H:i) erwartet. -->
  <TimeFieldRoot
    :id="id"
    v-slot="{ segments }"
    v-model="value"
    granularity="minute"
    :hour-cycle="24"
    locale="de-DE"
    :required="required"
    :disabled="disabled"
    :class="cn(
      inputVariants({ size }),
      'flex items-center gap-px tabular-nums',
      props.class,
    )"
  >
    <template v-for="segment in segments" :key="segment.part">
      <span v-if="segment.part === 'literal'" class="text-faint">{{ segment.value }}</span>
      <TimeFieldInput
        v-else
        :part="segment.part"
        class="rounded-[3px] px-[3px] outline-none focus:bg-accent focus:text-accent-foreground data-[placeholder]:text-faint"
      >
        {{ segment.value }}
      </TimeFieldInput>
    </template>

    <Clock class="ml-auto size-[13px] shrink-0 text-muted-foreground" aria-hidden="true" />
  </TimeFieldRoot>
</template>
