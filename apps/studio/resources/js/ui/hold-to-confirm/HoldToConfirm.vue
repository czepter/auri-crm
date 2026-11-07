<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import type { ButtonVariants } from "@/ui/button"
import { onBeforeUnmount, ref } from "vue"
import { cn } from "@/lib/utils"
import { Button } from "@/ui/button"

const props = withDefaults(defineProps<{
  variant?: ButtonVariants["variant"]
  tone?: ButtonVariants["tone"]
  duration?: number
  disabled?: boolean
  class?: HTMLAttributes["class"]
}>(), { duration: 1200 })

const emit = defineEmits<{ confirm: [] }>()

// Without the sweep there is nothing to see while holding, so the hold itself is longer.
const reduced = typeof matchMedia === "function" && matchMedia("(prefers-reduced-motion: reduce)").matches
const holdMs = reduced ? props.duration * 1.5 : props.duration

const holding = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

function start() {
  if (props.disabled || holding.value) return
  holding.value = true
  timer = setTimeout(() => {
    holding.value = false
    emit("confirm")
  }, holdMs)
}

function cancel() {
  clearTimeout(timer)
  holding.value = false
}

function onKeydown(event: KeyboardEvent) {
  if (event.key !== " " && event.key !== "Enter") return
  event.preventDefault()
  if (!event.repeat) start()
}

function onKeyup(event: KeyboardEvent) {
  if (event.key === " " || event.key === "Enter") cancel()
}

onBeforeUnmount(cancel)
</script>

<template>
  <Button
    type="button"
    data-slot="hold-to-confirm"
    :variant="variant"
    :tone="tone"
    :disabled="disabled"
    :class="cn('relative ml-0 select-none touch-none overflow-hidden', props.class)"
    @pointerdown.left="start"
    @pointerup="cancel"
    @pointerleave="cancel"
    @pointercancel="cancel"
    @keydown="onKeydown"
    @keyup="onKeyup"
    @blur="cancel"
    @click.prevent
    @contextmenu.prevent
  >
    <span
      v-if="!reduced"
      aria-hidden="true"
      class="pointer-events-none absolute inset-0 origin-left bg-black/20"
      :style="{
        transform: holding ? 'scaleX(1)' : 'scaleX(0)',
        transition: holding ? `transform ${holdMs}ms linear` : 'transform 300ms ease-out',
      }"
    />
    <span class="relative inline-flex items-center gap-[inherit]"><slot /></span>
  </Button>
</template>
