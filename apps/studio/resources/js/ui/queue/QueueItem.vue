<script setup lang="ts">
import { Link } from "@inertiajs/vue3"

/*
 * One row: who it is and how old it is on the first line, what it is about on
 * the second, their own words on the third. A row without an href asks the
 * page instead of navigating — the inbox opens a thread in place.
 */
const props = defineProps<{
    name: string
    meta?: string | null
    title?: string | null
    snippet?: string | null
    alert?: boolean
    active?: boolean
    href?: string
}>()

const emit = defineEmits<{ select: [] }>()

function click(event: MouseEvent) {
    if (props.href) return

    event.preventDefault()
    emit("select")
}
</script>

<template>
    <component
        :is="href ? Link : 'a'"
        :href="href ?? '#'"
        class="block px-3.5 py-[9px] text-inherit no-underline"
        :class="active
            ? 'bg-paper shadow-[inset_0_1px_0_var(--rule),inset_2px_0_0_var(--accent)]'
            : 'shadow-[inset_0_1px_0_var(--rule)]'"
        @click="click"
    >
        <span class="flex items-baseline gap-2">
            <span
                class="min-w-0 truncate text-[12.5px] leading-[1.47] font-semibold tracking-[-0.012em]"
            >
                {{ name }}
            </span>
            <span
                v-if="meta"
                class="ml-auto flex-none text-[11px] leading-[1.47] whitespace-nowrap"
                :class="alert ? 'text-action' : 'text-ink-2'"
            >
                {{ meta }}
            </span>
        </span>

        <span
            v-if="title"
            class="mt-px block text-[11.5px] leading-[1.47] text-ink-2"
        >
            {{ title }}
        </span>

        <span
            v-if="snippet"
            class="mt-0.5 line-clamp-2 block text-[11.5px] leading-[1.47] text-ink-2"
        >
            {{ snippet }}
        </span>
    </component>
</template>
