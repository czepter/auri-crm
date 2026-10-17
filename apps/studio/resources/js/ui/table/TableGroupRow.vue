<script setup lang="ts">
import { ChevronDown } from "lucide-vue-next"

/*
 * A heading inside the table body: the lists group by standing, by month or
 * by stage, and the group name is a row rather than a second table.
 *
 * Given `open`, the heading is the toggle for its rows; the list decides
 * which rows that hides.
 */
// `undefined` rather than Vue's cast to false: an absent `open` is a plain heading.
withDefaults(defineProps<{ colspan: number; open?: boolean }>(), { open: undefined })

defineEmits<{ toggle: [] }>()
</script>

<template>
  <tr data-slot="table-group-row">
    <td
      class="px-6 first:pl-6 last:pr-6 pt-5 pb-1.5 align-baseline text-small leading-none font-medium text-ink-2"
      :colspan="colspan"
    >
      <button
        v-if="open !== undefined"
        class="-ml-5 flex cursor-pointer items-center gap-1 border-0 bg-transparent p-0 font-[inherit] text-inherit hover:text-foreground"
        type="button"
        :aria-expanded="open"
        @click="$emit('toggle')"
      >
        <ChevronDown class="size-4 stroke-[1.8] transition-transform duration-[120ms]" :class="!open && '-rotate-90'" />
        <slot />
      </button>
      <slot v-else />
    </td>
  </tr>
</template>
