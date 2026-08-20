<script setup lang="ts">
/*
 * crm.css .hrow with the screen's name in it — the head every admin list
 * opens with. Six screens wrote the same two class lists out; the actions go
 * in the slot and push themselves right, as they did when .hrow was a rule.
 */
defineProps<{ title: string; kicker?: string }>()
</script>

<template>
    <div
        class="flex items-center gap-3 px-8 pt-[22px] max-[640px]:px-5 max-[640px]:pt-4"
    >
        <div>
            <div
                v-if="kicker"
                class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.05em] uppercase text-ink-2"
            >
                {{ kicker }}
            </div>
            <h1
                class="m-0 mt-0.5 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em] max-[640px]:text-[25px] max-[640px]:leading-[1.08]"
            >
                {{ title }}
            </h1>
        </div>
        <slot />
    </div>
</template>
