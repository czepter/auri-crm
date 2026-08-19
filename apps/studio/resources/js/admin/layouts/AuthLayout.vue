<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'

/*
 * tokens.css `.authwrap` / `.authcard` / `.authcard .brand` / `.authcard h1`,
 * as a layout. Ten screens repeated the four elements — sign-in, the password
 * pair, the two MFA steps, the invitation, the OAuth prompt and first-run
 * setup — and all ten are a card on an empty page with no navigation, because
 * there is none available to someone not signed in yet.
 */
const props = defineProps<{
  title: string
  /** `.authcard`'s own max-width where a screen widened it: 400, 420. */
  width?: string
}>()

const page = usePage()
</script>

<template>
  <!-- data-tokens without a value: this shell has no palette of its own, it
       only needs the marker that takes it out of the unlayered element rules
       in tokens.css. Without it the global h1 wins over the caption below. -->
  <div data-tokens class="flex min-h-screen items-center justify-center p-6">
    <div class="w-full" :class="props.width ?? 'max-w-[368px]'">
      <span class="block pb-6 text-[19px] leading-none font-bold tracking-[-0.035em] text-foreground">
        {{ page.props.brand }}
      </span>
      <h1 class="mb-1.5 text-[20px] leading-none font-bold tracking-[-0.04em]">{{ title }}</h1>
      <slot />
    </div>
  </div>
</template>
