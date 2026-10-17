<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/*
 * crm.css .rail — the trace beside the case: the week around the shoot, who
 * is on it, what hangs off it. Below 1080px it goes: the trace is what one
 * reads next to the work, not instead of it.
 *
 * A rail carrying operable controls rather than a trace needs the room for
 * them; that is what class is for.
 */
const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
    <aside
        data-slot="rail"
        :class="cn(
            'sticky top-0 ml-auto h-screen w-70 flex-none overflow-y-auto bg-sunk px-5 py-4 max-[1080px]:hidden',
            props.class,
        )"
    >
        <slot />
    </aside>
</template>
