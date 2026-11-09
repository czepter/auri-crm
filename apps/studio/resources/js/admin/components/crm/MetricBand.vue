<script setup lang="ts">
import { bandMetrics } from '@/admin/lib/projectOverview'

export interface BandMetric {
    label: string
    value: string | null
    unit?: string
    sub?: string
    subTone?: 'late'
    subHref?: string
}

const props = withDefaults(defineProps<{ metrics: BandMetric[]; columns?: number }>(), { columns: 2 })
</script>

<template>
    <div
        v-if="bandMetrics(props.metrics).length"
        class="grid overflow-hidden rounded-[var(--r)] bg-sunk"
        :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }"
    >
        <div v-for="metric in bandMetrics(props.metrics)" :key="metric.label" class="min-w-0 px-5 py-4">
            <p class="m-0 text-small text-ink-2">{{ metric.label }}</p>
            <b class="block truncate text-[26px] leading-[1.15] font-semibold tracking-[-0.02em] tabular-nums">
                {{ metric.value }}<span v-if="metric.unit" class="ml-2 text-small font-normal text-ink-2">{{ metric.unit }}</span>
            </b>
            <small v-if="metric.sub" class="mt-1 block truncate text-small" :class="metric.subTone === 'late' ? 'text-destructive' : 'text-ink-2'">
                <a v-if="metric.subHref" :href="metric.subHref" class="text-destructive no-underline hover:underline">{{ metric.sub }}</a>
                <template v-else>{{ metric.sub }}</template>
            </small>
        </div>
    </div>
</template>
