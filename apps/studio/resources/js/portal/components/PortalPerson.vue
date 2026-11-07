<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import PortalDot from '@/portal/components/PortalDot.vue'
import { initials } from '@/portal/lib/initials'
import type { Studio } from '@/portal/lib/studio'

/*
 * mockups/redesign/tokens.css `.person`. Stands on every client screen, not
 * only on the first day: someone ringing about an album two years later
 * looks for the same name as someone who has just enquired.
 *
 * The chips are drawn here rather than passed in as a slot, because all
 * three callers offered the same two — write and call — and the third of
 * them got the markup subtly wrong.
 */
const props = defineProps<{
  studio: Studio
  /** Where "Schreiben" goes — a booking's own thread, or the mailbox. */
  messageHref: string
}>()

const nav = computed(() => usePage().props.portalNav as { unread: number } | null | undefined)

// A card with an empty circle and an empty name is worse than no card: it
// reads as a failed load rather than as a studio that has not filled its
// settings in. The caller drops the whole section on the same condition —
// see lib/studio's `reachable`.
const named = computed(() => (props.studio.name ?? '').trim() !== '')

const CHIP
  = 'inline-flex items-center gap-2 rounded-[var(--r-pill)] border border-line-hover px-[15px] py-2.5 text-[13px] leading-none font-medium text-foreground no-underline transition-colors duration-200 hover:border-foreground hover:bg-foreground hover:text-background [&_svg]:size-3.5 [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:stroke-[1.5]'
</script>

<template>
  <div class="flex flex-wrap items-center gap-6 rounded-md border border-border p-6">
    <img
      v-if="studio.avatarUrl"
      :src="studio.avatarUrl"
      alt=""
      class="size-[54px] flex-none rounded-full bg-sunk object-cover"
    />
    <span
      v-else-if="named"
      class="grid size-[54px] flex-none place-items-center rounded-full bg-sunk font-serif text-[19px] leading-none text-muted-foreground"
    >
      {{ initials(studio.name) }}
    </span>

    <span v-if="named || studio.responseTime || studio.absence">
      <b v-if="named" class="block text-[15px] leading-[1.3] font-semibold tracking-[-0.01em]">{{ studio.name }}</b>
      <span v-if="studio.responseTime" class="text-[13.5px] text-muted-foreground">{{ studio.responseTime }}</span>
      <span v-if="studio.absence" class="mt-1 flex items-start gap-2 text-[13.5px] text-muted-foreground">
        <PortalDot state="due" class="mt-[7px]" />
        <span><span class="tabular-nums">{{ studio.absence.range }}</span> · {{ studio.absence.message }}</span>
      </span>
    </span>

    <span class="ml-auto flex flex-wrap gap-3 max-[700px]:ml-0 max-[700px]:w-full">
      <Link v-if="studio.messagingEnabled" :class="CHIP" :href="messageHref">
        <svg viewBox="0 0 24 24"><path d="M4 6h16v12H4zM4 7l8 6 8-6" /></svg>
        {{ $t('portal.personWrite') }}
        <span
          v-if="nav?.unread"
          class="min-w-[18px] rounded-[var(--r-pill)] bg-action px-[5px] text-center text-[11px] leading-[18px] font-semibold text-white"
        >{{ nav.unread }}</span>
      </Link>
      <a v-if="studio.phone" :class="CHIP" :href="`tel:${studio.phone.replace(/\s/g, '')}`">
        <svg viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a1 1 0 01-1 1A16 16 0 014 5a1 1 0 011-1z" /></svg>
        {{ studio.phone }}
      </a>
      <a v-if="studio.email" :class="CHIP" :href="`mailto:${studio.email}`">
        <!-- Das @ und nicht der Umschlag: der steht schon auf „Schreiben“. -->
        <svg viewBox="0 0 24 24"><path d="M16 12a4 4 0 10-1.2 2.9M16 8v5a2.5 2.5 0 005 0v-1a9 9 0 10-3.4 7.1" /></svg>
        {{ studio.email }}
      </a>
    </span>
  </div>
</template>
