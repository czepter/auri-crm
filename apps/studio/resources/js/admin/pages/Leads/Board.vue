<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import LeadCard from '@/admin/components/LeadCard.vue'
import { Button } from '@/ui/button'

type Column = { stage: string; label: string; cards: App.Crm.Data.LeadCardData[] }

const props = defineProps<{ columns: Column[] }>()

// The next column along is where a card advances to, so the board's own order
// defines the funnel rather than the card knowing it.
function nextStage(index: number): string | null {
  return props.columns[index + 1]?.stage ?? null
}

// Which card is in the air, and which column it came from. dataTransfer cannot
// be read during dragover, so the column it started in has to live here for the
// drop targets to know whether they are legal.
const dragging = ref<{ id: string; leadId: string; index: number } | null>(null)
const over = ref<number | null>(null)

// Both directions, matching LeadStage::canTransitionTo — dragging left is how
// a mis-drop is undone. Only the column the card already sits in is refused,
// the one move the action rejects.
function canDrop(index: number): boolean {
  return dragging.value !== null && index !== dragging.value.index
}

function onDragStart(event: DragEvent, card: App.Crm.Data.LeadCardData, index: number) {
  dragging.value = { id: card.id, leadId: card.leadId, index }
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

// A column only becomes a drop target by preventing the dragover default, so
// the illegal ones are inert without needing a disabled state of their own.
function onDragOver(event: DragEvent, index: number) {
  if (!canDrop(index)) return
  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  over.value = index
}

function onDrop(index: number) {
  if (!canDrop(index)) return

  router.post(
    `/anfragen/${dragging.value!.leadId}/stufe`,
    { stage: props.columns[index].stage },
    { preserveScroll: true },
  )

  reset()
}

function reset() {
  dragging.value = null
  over.value = null
}
</script>

<template>
  <AdminLayout active="anfragen">
    <div class="hrow" style="margin-bottom: var(--s5)">
      <h1>{{ $t('board.title') }}</h1>
      <Button :as="Link" href="/anfragen/neu">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('board.create') }}
      </Button>
    </div>

    <div class="board">
      <section
        v-for="(column, index) in columns"
        :key="column.stage"
        class="col"
        :class="{ drop: over === index }"
        @dragover="onDragOver($event, index)"
        @drop.prevent="onDrop(index)"
      >
        <div class="ch">
          <b>{{ column.label }}</b>
          <span class="n">{{ column.cards.length }}</span>
        </div>

        <!-- draggable and the handlers fall through LeadCard onto its own root
             anchor, so the card stays a link and gains a grip in one prop. -->
        <LeadCard
          v-for="card in column.cards"
          :key="card.id"
          :card="card"
          :next-stage="nextStage(index)"
          draggable="true"
          :class="{ dragging: dragging?.id === card.id }"
          @dragstart="onDragStart($event, card, index)"
          @dragend="reset"
        />
      </section>
    </div>
  </AdminLayout>
</template>
