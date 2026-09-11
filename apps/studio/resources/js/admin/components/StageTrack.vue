<script setup lang="ts">
/*
 * The way from inquiry to delivery in one row, in the head of every project
 * tab. ReadStageTrack decides the steps and where the project stands.
 */
import { computed } from "vue"

const props = defineProps<{
    steps: { key: string; label: string; pipeline: string; state: string }[]
}>()

const leadSteps = computed(() => props.steps.filter((step) => step.pipeline === "lead").length)
</script>

<template>
    <div
        class="grid gap-x-1 gap-y-2"
        :style="{ gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))` }"
    >
        <span
            v-if="leadSteps"
            class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2"
            :style="{ gridColumn: `span ${leadSteps}` }"
        >{{ $t("project.lead") }}</span>
        <span
            class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2"
            :style="{ gridColumn: `span ${steps.length - leadSteps}` }"
        >{{ $t("project.trackProject") }}</span>

        <div
            v-for="step in steps"
            :key="`${step.pipeline}-${step.key}`"
            class="flex min-w-0 flex-col gap-[7px]"
            :aria-current="step.state === 'current' ? 'step' : undefined"
        >
            <span class="h-1 rounded-full" :class="step.state === 'todo' ? 'bg-rule' : 'bg-action'" />
            <span
                class="text-[11.5px] leading-[1.3] text-pretty"
                :class="{
                    'font-semibold text-foreground': step.state === 'current',
                    'text-ink-2': step.state === 'done',
                    'text-faint': step.state === 'todo',
                }"
            >{{ step.label }}</span>
        </div>
    </div>
</template>
