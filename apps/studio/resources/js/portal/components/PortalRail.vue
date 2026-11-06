<script setup lang="ts">
import PortalLabel from '@/portal/components/PortalLabel.vue'

/*
 * The one thing that waits, beside the page from 1100px and above it below.
 * Inside PortalWrap: the default slot is the page, `rail` is the column.
 * With a kicker the rail is a card (kicker, amount, line, then the slot);
 * without one the slot stands as it is.
 */
defineProps<{
  kicker?: string
  amount?: string
  note?: string
}>()
</script>

<template>
  <div class="grid min-[1100px]:grid-cols-[minmax(0,1fr)_360px] min-[1100px]:gap-x-16">
    <div class="min-w-0">
      <slot />
    </div>

    <aside class="order-first mb-12 min-[1100px]:order-none min-[1100px]:sticky min-[1100px]:top-[88px] min-[1100px]:mb-0 min-[1100px]:self-start">
      <div v-if="kicker" class="rounded-md border border-border p-6">
        <PortalLabel>{{ kicker }}</PortalLabel>
        <p v-if="amount" class="mt-4 font-serif text-[34px] leading-[1.1] tracking-[-0.015em] tabular-nums">{{ amount }}</p>
        <p v-if="note" class="mt-2 text-[13.5px] text-muted-foreground">{{ note }}</p>
        <div class="mt-6 grid gap-4">
          <slot name="rail" />
        </div>
      </div>
      <slot v-else name="rail" />
    </aside>
  </div>
</template>
