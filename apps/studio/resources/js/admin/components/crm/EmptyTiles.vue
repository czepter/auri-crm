<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Camera, ClipboardList, FileText, Image, ListChecks, StickyNote } from 'lucide-vue-next'

defineProps<{ tiles: { key: 'shoot' | 'quote' | 'task' | 'form' | 'gallery' | 'note'; label: string; href: string }[] }>()

const ICON = { shoot: Camera, quote: FileText, task: ListChecks, form: ClipboardList, gallery: Image, note: StickyNote }
</script>

<template>
    <section class="surface-card p-6 ">
        <div class="grid gap-2" :style="{ gridTemplateColumns: `repeat(${tiles.length}, minmax(0, 1fr))` }">
            <Link
                v-for="tile in tiles"
                :key="tile.key"
                :href="tile.href"
                class="flex min-h-[104px] flex-col items-center justify-center gap-2 rounded-[var(--r)] bg-sunk text-ui font-medium text-foreground no-underline hover:bg-sunk-2 max-[640px]:min-h-20"
            >
                <component :is="ICON[tile.key]" class="size-5 text-ink-2" />
                {{ tile.label }}
            </Link>
        </div>
    </section>
</template>
