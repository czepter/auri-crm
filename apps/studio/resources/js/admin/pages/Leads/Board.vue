<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import LeadCard from '@/admin/components/LeadCard.vue'

type Column = { stage: string; label: string; cards: App.Crm.Data.LeadCardData[] }

const props = defineProps<{ columns: Column[] }>()

// The next column along is where a card advances to, so the board's own order
// defines the funnel rather than the card knowing it.
function nextStage(index: number): string | null {
  return props.columns[index + 1]?.stage ?? null
}
</script>

<template>
  <AdminLayout active="anfragen">
    <div class="hrow" style="margin-bottom: var(--s5)">
      <h1>{{ $t('board.title') }}</h1>
      <Link class="btn" href="/anfragen/neu">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('board.create') }}
      </Link>
    </div>

    <div class="board">
      <section v-for="(column, index) in columns" :key="column.stage" class="col">
        <div class="ch">
          <b>{{ column.label }}</b>
          <span class="n">{{ column.cards.length }}</span>
        </div>

        <LeadCard
          v-for="card in column.cards"
          :key="card.id"
          :card="card"
          :next-stage="nextStage(index)"
        />
      </section>
    </div>
  </AdminLayout>
</template>
