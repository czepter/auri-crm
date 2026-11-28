<script setup lang="ts">
/*
 * crm.css .panel — a field on the dashboard: a card surface with a name, how
 * many there are, and at most one action. `bare` is the sheet's own
 * `.panel.bare`: the most important field needs no container.
 */
defineProps<{ title: string; count?: number | string | null; bare?: boolean }>()
</script>

<template>
    <section
        class="overflow-hidden rounded-[var(--r)] px-6 py-5"
        :class="bare ? 'bg-transparent' : 'surface-card'"
    >
        <header class="mb-2 flex items-baseline gap-[9px]">
            <h2 class="m-0 text-[15px] leading-[1.2] font-semibold tracking-[-0.015em]">
                {{ title }}
            </h2>
            <span
                v-if="count !== undefined && count !== null"
                class="text-small tabular-nums text-ink-2"
            >
                {{ count }}
            </span>
            <slot name="action" />
        </header>
        <slot />
    </section>
</template>
