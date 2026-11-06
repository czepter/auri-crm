<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

/*
 * The head a sub-screen wears below 980px, in place of PortalHeader's bar.
 *
 * A conversation is a tool, not a place: the client came to answer, and every
 * row of chrome above the newest line is a row they scroll past. Brand, page
 * title and booking chip stacked under the bar cost 166px of a 390px screen
 * before a message appears. One bar carries all of it — the way back, what
 * this is, and which booking it belongs to.
 *
 * The bookings keep PortalHeader and its switcher: a booking is a place, and
 * a place is worth a title image. Two heads on this surface, and the line
 * between them is that difference rather than a breakpoint.
 */
defineProps<{
  /** Where the chevron goes. */
  back: string
  /** What the chevron is called, for anyone who cannot see it. */
  backLabel: string
  title: string
  /** The quieter second line — which booking this belongs to. */
  note?: string | null
}>()
</script>

<template>
  <div
    class="sticky top-0 z-30 flex items-center gap-3 border-b border-border bg-[color-mix(in_srgb,var(--bg)_97%,transparent)] py-[9px] pr-4 pl-2 backdrop-blur-[12px] min-[980px]:hidden"
  >
    <Link
      class="grid size-11 flex-none place-items-center text-foreground no-underline"
      :href="back"
      :aria-label="backLabel"
    >
      <svg class="size-5 fill-none stroke-current stroke-[1.7] [stroke-linecap:round] [stroke-linejoin:round]" viewBox="0 0 24 24">
        <path d="M15 6l-6 6 6 6" />
      </svg>
    </Link>

    <span class="min-w-0 flex-1">
      <b class="block truncate text-[16px] leading-[1.25] font-semibold tracking-[-0.014em]">{{ title }}</b>
      <span v-if="note" class="mt-px block truncate text-[11.5px] leading-[1.3] text-muted-foreground">{{ note }}</span>
    </span>
  </div>
</template>
