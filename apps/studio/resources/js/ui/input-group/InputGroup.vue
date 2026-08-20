<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/*
 * shadcn's input-group, as far as this product uses it: a field whose shell
 * carries a fixed piece of the value — `https://` before a website, a unit
 * after a number — so the part that never varies is not something to type.
 *
 * The border, the hover and the focus colour move from the input to this
 * wrapper, which is why InputGroupInput draws none of its own. Everything
 * else is the same rule as ui/input's `inputVariants`; a group and a plain
 * field standing next to each other have to measure the same.
 */
const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <div
    data-slot="input-group"
    :class="cn(
      'flex w-full min-w-0 items-center rounded-md border border-input bg-background',
      'transition-[border-color] duration-[120ms] ease-out',
      'hover:border-line-hover focus-within:border-ring',
      'has-[input:disabled]:cursor-not-allowed has-[input:disabled]:opacity-50',
      'has-[[aria-invalid=true]]:border-destructive',
      props.class,
    )"
  >
    <slot />
  </div>
</template>
