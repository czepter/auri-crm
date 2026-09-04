<script setup lang="ts">
/*
 * crm.css .band .cell — one key figure of a project, with the proof of where
 * it comes from underneath. Six of them stand across the width rather than in
 * a narrow column: master data is read along a line, not down one.
 */
defineProps<{ label: string; sub?: string | null }>()
</script>

<template>
    <div
        class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none"
    >
        <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">
            {{ label }}
            <!-- A derived figure says where it comes from while editing —
                 otherwise one hunts for the field that does not exist. -->
            <span class="tracking-normal normal-case text-mark"><slot name="derived" /></span>
        </div>
        <slot />
        <div v-if="sub" class="mt-[3px] text-[11.5px] text-ink-2">{{ sub }}</div>
    </div>
</template>
