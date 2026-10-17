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
    <div class="px-6 pt-5 max-[640px]:px-5 max-[640px]:pt-4">
        <div
            v-if="kicker"
            class="mb-1 text-small leading-none font-medium text-ink-2"
        >
            <Link v-if="kickerHref" class="text-inherit no-underline hover:text-foreground" :href="kickerHref">{{ kicker }}</Link>
            <template v-else>{{ kicker }}</template>
        </div>
        <div class="flex items-center gap-3 max-[640px]:flex-wrap">
            <h1
                class="m-0 text-[20px] leading-7 font-semibold tracking-[-0.02em] max-[640px]:w-full"
            >
                {{ title }}
            </h1>
            <slot />
        </div>
    </div>
</template>
