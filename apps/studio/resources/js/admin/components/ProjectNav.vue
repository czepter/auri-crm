<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

/*
 * The tools of one project. Written as a column rather than a strip over the
 * content: six entries with a count beside each need a left edge to align on,
 * which a horizontal row cannot give them.
 *
 * Below 640px it lies down and scrolls sideways — navigation, unlike the rail
 * beside it, is never the thing that disappears when the screen narrows.
 */
defineProps<{ tabs: App.Crm.Data.ProjectHeaderData['tabs']; active: string }>()
</script>

<template>
  <nav
    class="flex w-[150px] flex-none flex-col gap-0.5 border-r border-border px-2.5 py-4 max-[640px]:w-full max-[640px]:flex-row max-[640px]:self-start max-[640px]:overflow-x-auto max-[640px]:border-r-0 max-[640px]:border-b max-[640px]:px-5 max-[640px]:py-2.5"
  >
    <Link
      v-for="tab in tabs"
      :key="tab.key"
      class="flex items-center gap-2.5 rounded-[7px] px-2.5 py-[7px] text-[13px] leading-none whitespace-nowrap no-underline transition-[background-color] duration-[.12s] ease-out hover:bg-muted"
      :class="
        tab.key === active
          ? 'bg-muted font-semibold text-foreground'
          : 'font-normal text-muted-foreground'
      "
      :href="tab.href"
    >
      {{ $t(tab.label) }}
      <span v-if="tab.count" class="ml-auto text-[11.5px] tabular-nums text-ink-2">{{ tab.count }}</span>
    </Link>
  </nav>
</template>
