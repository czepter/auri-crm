<script setup lang="ts">
/*
 * A heading inside the table body: the lists group by standing, by month or
 * by stage, and the group name is a row rather than a second table.
 */
defineProps<{ colspan: number }>()
</script>

<template>
  <tr data-slot="table-group-row">
    <td
      class="bg-sunk px-8 pt-4 pb-1.5 align-baseline text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2"
      :colspan="colspan"
    >
      <slot />
    </td>
  </tr>
</template>
