<script setup lang="ts">
import { Link } from "@inertiajs/vue3"

/*
 * crm.css .rail .item — one entry in the trace: what it is, and when it runs
 * out. A link where there is somewhere to go, plain text where there is not.
 */
defineProps<{ title: string; href?: string; meta?: string | null }>()
</script>

<template>
    <div class="py-2 border-t border-rule">
        <component
            :is="href ? Link : 'div'"
            :href="href"
            class="text-small leading-[1.47] font-semibold tracking-[-0.012em]"
        >
            {{ title }}
        </component>
        <div v-if="meta" class="mt-px text-small leading-[1.47] text-ink-2">
            {{ meta }}
        </div>
    </div>
</template>
