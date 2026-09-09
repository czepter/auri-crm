<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"
import { inputVariants } from "@/ui/input"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <!-- forms.css draws textarea.inp as the same field with more room, so this
       borrows inputVariants rather than restating the border and focus rules
       a second time — two copies is how the two controls drift apart. -->
  <textarea
    v-model="modelValue"
    data-slot="textarea"
    :class="
      cn(
        inputVariants(),
        'min-h-[104px] resize-y leading-[1.5]',
        props.class,
      )
    "
  />
</template>
