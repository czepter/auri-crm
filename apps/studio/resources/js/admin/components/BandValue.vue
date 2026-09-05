<script setup lang="ts">
import { cn } from "@/lib/utils"

/*
 * The figure itself. `plain` is crm.css .val.plain — a word rather than a
 * number, which carries less weight because it is not the thing being
 * compared down the row.
 */
const props = defineProps<{ plain?: boolean; warn?: boolean }>()
</script>

<template>
    <div
        :class="
            cn(
                'mt-[5px] leading-[1.2] tracking-[-0.02em] tabular-nums',
                props.plain ? 'text-[15px] font-medium' : 'text-[17px] font-semibold',
                props.warn && 'text-action',
            )
        "
    >
        <slot />
    </div>
</template>
