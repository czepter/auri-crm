<script setup lang="ts">
/*
 * crm.css `.detail section` and its header — the grammar every part of a case
 * is written in: a name, how many there are, and the one action that belongs
 * to that part. Five sections on the inquiry screen wrote it out by hand.
 */
defineProps<{ title: string; count?: number | string | null }>()
</script>

<template>
    <section class="mt-[22px]">
        <header class="mb-[7px] flex items-baseline gap-[9px]">
            <h2 class="m-0 text-[15px] leading-[1.2] font-semibold tracking-[-0.02em]">
                {{ title }}
            </h2>
            <span
                v-if="count !== undefined && count !== null"
                class="text-[11.5px] leading-[1.47] tabular-nums text-ink-2"
            >
                {{ count }}
            </span>
            <slot name="action" />
        </header>
        <slot />
    </section>
</template>
