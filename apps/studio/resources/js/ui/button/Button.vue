<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ButtonVariants } from "."
import { Primitive } from "reka-ui"
import { cn } from "@/lib/utils"
import { buttonVariants } from "."

interface Props extends PrimitiveProps {
  variant?: ButtonVariants["variant"]
  tone?: ButtonVariants["tone"]
  class?: HTMLAttributes["class"]
}

/*
 * variant and tone are defaulted here as well as in the cva so the data
 * attributes always render — the stylesheets that still reach a button by
 * context (portal.css .wide, admin-extra.css .qbrow) select on them.
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
    :class="cn(buttonVariants({ variant, tone }), props.class)"
  >
    <slot />
  </Primitive>
</template>
