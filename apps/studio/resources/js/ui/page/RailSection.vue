<script setup lang="ts">
/*
 * One group in the trace. The gap between two of them is the sheet's own
 * `.rail .grp + h2` — without it the groups run into each other, as in
 * mockups/redesign/crm-anfrage.html they do not.
 */
defineProps<{ title: string }>()
</script>

<template>
    <section class="[&+&]:mt-5">
        <h2
            class="m-0 mb-[9px] text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2"
        >
            {{ title }}
        </h2>
        <slot />
    </section>
</template>
