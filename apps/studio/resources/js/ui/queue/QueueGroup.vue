<script setup lang="ts">
/* An empty group is not a heading with a zero beside it — it is absent. */
defineProps<{ title: string; count: number }>()
</script>

<template>
    <h2 v-if="count" class="m-0 flex items-baseline px-3.5 pb-2 [a+&]:pt-5">
        <b class="text-[12.5px] leading-none font-semibold tracking-[-0.012em]">
            {{ title }}
        </b>
        <span
            class="ml-auto text-[11px] leading-[1.47] tabular-nums text-ink-2"
        >
            {{ count }}
        </span>
    </h2>
</template>
