<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/*
 * mockups/redesign/tokens.css `.page`. One measure for every screen on this
 * surface — nothing sets a width of its own, and the screens that want a
 * narrower column (the account's 680px, a project's 880px band, the
 * inquiry's 620px letter) narrow inside it rather than beside it.
 *
 * `grow` is what makes it the child that takes up the slack in
 * PortalShell's column, so a short screen still puts its footer at the
 * bottom of the window.
 */
const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <main :class="cn('mx-auto w-full max-w-[var(--page)] grow px-6', props.class)">
    <slot />
  </main>
</template>
