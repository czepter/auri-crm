<script setup lang="ts">
import { cn } from "@/lib/utils"

/*
 * crm.css .stream .ev — one line of the trace. What somebody wrote and what
 * the system recorded are read together or not at all, so they are the same
 * row; only the colour says which is which.
 */
const props = defineProps<{ when: string; system?: boolean }>()
</script>

<template>
    <div class="relative flex gap-3.5 py-2.5 shadow-[inset_0_1px_0_var(--rule)]">
        <span class="w-[110px] flex-none text-[12px] leading-[1.3] tabular-nums text-ink-2">
            {{ when }}
        </span>
        <span
            :class="cn('min-w-0 flex-1 text-[13px] leading-[1.47]', props.system && 'text-ink-2')"
        >
            <slot />
        </span>
    </div>
</template>
