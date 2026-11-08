<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ButtonVariants } from "."
import { Check, X } from "lucide-vue-next"
import { Primitive } from "reka-ui"
import { cn } from "@/lib/utils"
import { buttonVariants } from "."

interface Props extends PrimitiveProps {
  variant?: ButtonVariants["variant"]
  tone?: ButtonVariants["tone"]
  state?: "idle" | "loading" | "success" | "error"
  class?: HTMLAttributes["class"]
}

/*
 * variant and tone are defaulted here as well as in the cva so the data
 * attributes always render — the stylesheets that still reach a button by
 * context (admin-extra.css .qbrow) select on them.
 */
const props = withDefaults(defineProps<Props>(), {
  as: "button",
  variant: "default",
  tone: "default",
})
</script>

<template>
  <Primitive
    data-slot="button"
    :data-variant="variant"
    :data-tone="tone"
    :as="as"
    :as-child="asChild"
    :class="cn(buttonVariants({ variant, tone }), state === 'loading' && 'pointer-events-none', props.class)"
    :aria-busy="state === 'loading' || undefined"
  >
    <slot v-if="!state" />
    <span v-else class="inline-grid items-center justify-items-center">
      <span
        class="col-start-1 row-start-1 inline-flex items-center gap-[inherit] transition-opacity duration-150"
        :class="state === 'idle' ? 'opacity-100' : 'opacity-0'"
      >
        <slot />
      </span>
      <span v-if="state !== 'idle'" aria-hidden="true" class="col-start-1 row-start-1 inline-flex">
        <span v-if="state === 'loading'" class="size-[15px] animate-spin rounded-full border-2 border-current border-r-transparent motion-reduce:animate-none" />
        <Check v-else-if="state === 'success'" />
        <X v-else />
      </span>
    </span>
  </Primitive>
</template>
