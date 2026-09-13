<script setup lang="ts">
import { GripVertical } from 'lucide-vue-next'

const emit = defineEmits<{ dragStart: []; dragEnd: []; moveUp: []; moveDown: [] }>()

// Der Griff allein wäre das Ziehbild; gezogen wird die ganze Karte.
function onDragStart(event: DragEvent) {
  const card = (event.currentTarget as HTMLElement).closest('[data-position]')

  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    if (card) event.dataTransfer.setDragImage(card, 16, 24)
  }

  emit('dragStart')
}
</script>

<template>
  <button
    type="button"
    draggable="true"
    class="-mr-3 ml-2 flex h-8 w-6 flex-none cursor-grab items-center justify-center rounded-[6px] text-faint hover:text-ink-2 active:cursor-grabbing"
    :aria-label="$t('quotes.move')"
    @dragstart="onDragStart"
    @dragend="emit('dragEnd')"
    @keydown.up.prevent="emit('moveUp')"
    @keydown.down.prevent="emit('moveDown')"
  >
    <GripVertical class="size-4" />
  </button>
</template>
