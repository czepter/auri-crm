<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import PortalDot from '@/portal/components/PortalDot.vue'

/*
 * mockups/redesign/tokens.css `.item` — one shape for every list on this
 * surface where something can be open: what is waiting on the start screen,
 * the paperwork inside a booking, the questionnaire in an inquiry, the two
 * privacy requests in the account. The client learns the row once.
 *
 * The state stands in the row rather than behind the click, which is the
 * whole point: three grey text links were what this replaced.
 */
withDefaults(defineProps<{
  title: string
  href?: string | null
  /** Inertia visit for portal-internal paths; a plain <a> for downloads and files. */
  inertia?: boolean
  state?: 'open' | 'due' | 'done' | 'neutral' | 'late'
  /** The line under the title. Carries the dot. */
  note?: string | null
  /** A second, quieter line — which booking a row came from. */
  where?: string | null
  /** Right-hand column: a sum, a date, a file kind. */
  aside?: string | null
  /** Draws the aside in the action colour, for money that is still owed. */
  asideOpen?: boolean
}>(), {
  href: null,
  inertia: true,
  state: 'neutral',
  note: null,
  where: null,
  aside: null,
  asideOpen: false,
})
</script>

<template>
  <component
    :is="href === null ? 'div' : inertia ? Link : 'a'"
    :href="href ?? undefined"
    class="group -mx-4 grid grid-cols-[1fr_auto_auto] items-center gap-x-6 gap-y-1.5 rounded-[var(--r-sm)] border-b border-border px-4 py-6 text-inherit no-underline transition-colors duration-200 hover:bg-sunk max-[820px]:grid-cols-[1fr_auto]"
  >
    <span class="col-start-1 row-start-1 min-w-0">
      <span class="block font-serif text-[22px] leading-[1.2] tracking-[-0.01em]">{{ title }}</span>
      <span v-if="note" class="mt-1 flex items-center gap-2 text-[13.5px] text-muted-foreground">
        <PortalDot :state="state" />{{ note }}
      </span>
      <span v-if="where" class="mt-[5px] block text-[12.5px] text-muted-foreground">{{ where }}</span>
    </span>

    <span
      v-if="aside"
      class="col-start-2 row-start-1 text-[15px] leading-none font-semibold tracking-[-0.01em] tabular-nums whitespace-nowrap max-[820px]:col-start-1 max-[820px]:row-start-2 max-[820px]:text-[14px]"
      :class="asideOpen ? 'text-foreground' : 'text-muted-foreground'"
    >{{ aside }}</span>

    <span
      class="col-start-3 row-start-1 justify-self-end max-[820px]:col-start-2"
      :class="href && 'text-mark transition-[color,transform] duration-300 group-hover:translate-x-[3px] group-hover:text-foreground'"
    >
      <svg v-if="href" class="size-[18px] fill-none stroke-current stroke-[1.4]" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      <slot name="action" />
    </span>
  </component>
</template>
