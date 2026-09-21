<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import type { InputVariants } from "."
import { ref } from "vue"
import { useVModel } from "@vueuse/core"
import type { Rule } from "@/lib/rules"
import { cn } from "@/lib/utils"
import { inputVariants } from "."
import { useRule } from "./useRule"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  size?: InputVariants["size"]
  rule?: Rule
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})

const el = ref<HTMLInputElement | null>(null)
useRule(el, () => props.rule, () => modelValue.value)
</script>

<template>
  <input
    ref="el"
    v-model="modelValue"
    data-slot="input"
    :class="cn(inputVariants({ size }), props.class)"
  >
</template>
