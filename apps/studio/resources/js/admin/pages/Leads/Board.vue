<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
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
  <main class="space-y-6 p-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">{{ $t('board.title') }}</h1>
      <Button as-child>
        <Link href="/anfragen/neu">{{ $t('board.create') }}</Link>
      </Button>
    </div>

    <div class="grid grid-cols-6 gap-8 overflow-x-auto">
      <section v-for="(column, index) in columns" :key="column.stage" class="min-w-56 space-y-1.5">
        <header class="flex items-baseline gap-2 pb-2">
          <h2 class="text-sm font-semibold">{{ column.label }}</h2>
          <span class="rounded px-1.5 text-xs tabular-nums text-muted-foreground">
            {{ column.cards.length }}
          </span>
        </header>

        <LeadCard
          v-for="card in column.cards"
          :key="card.id"
          :card="card"
          :next-stage="nextStage(index)"
        />

        <p v-if="!column.cards.length" class="text-xs text-muted-foreground">
          {{ $t('board.empty') }}
        </p>
      </section>
    </div>
  </main>
</template>
