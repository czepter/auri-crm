<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Button } from '@/ui/button'

defineProps<{ title: string; meta: string; late?: string | null; action: string; href?: string }>()
defineEmits<{ done: [] }>()
</script>

<template>
    <div class="flex flex-col justify-center gap-4 rounded-[var(--r)] bg-[var(--accent-bg)] px-6 py-5">
        <div>
            <span class="text-small text-ink-2">{{ $t('project.nextStep') }}</span>
            <b class="block text-body leading-[1.3] font-semibold">{{ title }}</b>
            <span class="text-small text-ink-2">
                {{ meta }}<template v-if="late"> · <span class="text-destructive">{{ late }}</span></template>
            </span>
        </div>
        <Button
            variant="pill-muted"
            class="min-h-10 self-start px-4"
            type="button"
            :as-child="!!href"
            @click="!href && $emit('done')"
        >
            <Link v-if="href" :href="href">{{ action }}</Link>
            <template v-else>{{ action }}</template>
        </Button>
    </div>
</template>
