<script setup lang="ts">
/*
 * The way from inquiry to delivery in one row, in the head of every project
 * tab. ReadStageTrack decides the steps and where the project stands.
 *
 * Only the current step and the one after it are named; thirteen names in a
 * column this wide ran into each other.
 */
import { computed } from "vue"

const props = defineProps<{
    steps: { key: string; label: string; pipeline: string; state: string }[]
}>()

const at = computed(() => props.steps.findIndex((step) => step.state === "current"))
</script>

<template>
    <ol class="m-0 flex list-none items-start gap-[5px] p-0">
        <template v-for="(step, index) in steps" :key="`${step.pipeline}-${step.key}`">
            <li
                v-if="index > 0 && step.pipeline !== steps[index - 1].pipeline"
                aria-hidden="true"
                class="mx-[3px] -mt-[3px] h-3 w-px flex-none bg-rule"
            />
            <li
                v-if="at >= 0 && (index === at || index === at + 1)"
                class="flex flex-col gap-[7px]"
                :class="index === at ? 'flex-none' : 'min-w-0'"
                :aria-current="index === at ? 'step' : undefined"
            >
                <span class="h-1.5 rounded-full" :class="index === at ? 'bg-action' : 'bg-rule'" />
                <span
                    class="truncate pr-1.5 text-[11.5px] leading-[1.3]"
                    :class="index === at ? 'font-semibold text-foreground' : 'text-faint'"
                >{{ step.label }}</span>
            </li>
            <li
                v-else
                class="size-1.5 flex-none rounded-full"
                :class="step.state === 'done' ? 'bg-action' : 'bg-rule'"
                :title="step.label"
            >
                <span class="sr-only">{{ step.label }}</span>
            </li>
        </template>
    </ol>
</template>
