<script setup lang="ts" generic="T">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { cap } from '@/admin/lib/projectOverview'

const props = defineProps<{ items: T[]; limit: number; moreHref: string; total?: number }>()

const capped = computed(() => cap(props.items, props.limit))
const count = computed(() => props.total ?? capped.value.total)
</script>

<template>
    <slot v-for="(item, index) in capped.shown" :item="item" :index="index" />
    <Link
        v-if="count > capped.shown.length"
        :href="moreHref"
        class="inline-flex min-h-10 items-center text-ui font-medium text-ring no-underline hover:underline"
    >
        {{ $t('project.showAllCount', { count }) }}
    </Link>
</template>
