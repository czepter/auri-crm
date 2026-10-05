<script setup lang="ts">
/*
 * crm.css .hrow with the screen's name in it — the head every admin list
 * opens with. Six screens wrote the same two class lists out; the actions go
 * in the slot and push themselves right, as they did when .hrow was a rule.
 */
import { Link } from '@inertiajs/vue3'

defineProps<{ title: string; kicker?: string; kickerHref?: string }>()
</script>

<template>
    <div class="px-8 pt-[22px] max-[640px]:px-5 max-[640px]:pt-4">
        <div
            v-if="kicker"
            class="mb-[5px] text-small leading-none font-semibold tracking-[0.05em] uppercase text-ink-2"
        >
            <Link v-if="kickerHref" class="text-inherit no-underline hover:text-foreground" :href="kickerHref">{{ kicker }}</Link>
            <template v-else>{{ kicker }}</template>
        </div>
        <div class="flex items-center gap-3 max-[640px]:flex-wrap">
            <h1
                class="m-0 mt-0.5 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em] max-[640px]:w-full max-[640px]:text-[25px] max-[640px]:leading-[1.08]"
            >
                {{ title }}
            </h1>
            <slot />
        </div>
    </div>
</template>
