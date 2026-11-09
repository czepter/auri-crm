<script setup lang="ts">
import { applyClick, type SelectionState } from '@/admin/lib/gallerySelection'

const props = defineProps<{
  assets: { id: string; src: string; filename: string }[]
}>()

const selection = defineModel<SelectionState>({ required: true })

function click(id: string, event: MouseEvent) {
  selection.value = applyClick(selection.value, props.assets.map((asset) => asset.id), id, {
    shift: event.shiftKey,
    meta: event.metaKey || event.ctrlKey,
  })
}
</script>

<template>
  <ul class="grid max-h-72 grid-cols-4 gap-1.5 overflow-y-auto sm:grid-cols-6">
    <li v-for="asset in assets" :key="asset.id">
      <button
        type="button"
        class="block aspect-square w-full overflow-hidden rounded-sm"
        :class="selection.selected.has(asset.id) && 'outline-2 -outline-offset-2 outline-foreground'"
        :aria-pressed="selection.selected.has(asset.id)"
        :aria-label="asset.filename"
        @click="click(asset.id, $event)"
      >
        <img :src="asset.src" :alt="asset.filename" loading="lazy" class="size-full object-cover" />
      </button>
    </li>
  </ul>
</template>
