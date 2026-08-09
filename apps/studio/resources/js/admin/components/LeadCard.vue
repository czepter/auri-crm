<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps<{ card: App.Crm.Data.LeadCardData; nextStage: string | null }>()

// Vision §7 nudges at 3 and 7 days. Colouring the age is what lets the board
// show what needs chasing without a separate view.
const stale = computed(() => props.card.daysInStage >= 7)

const dateLabel = computed(() =>
  props.card.date ? new Date(props.card.date).toLocaleDateString('de-DE') : null,
)

function advance() {
  if (props.nextStage) {
    router.post(`/anfragen/${props.card.id}/stufe`, { stage: props.nextStage })
  }
}
</script>

<template>
  <article class="group rounded-lg border p-3 transition hover:border-foreground/30">
    <p class="text-[11px] uppercase tracking-wide text-muted-foreground">{{ card.type }}</p>
    <h3 class="text-sm font-medium">
      <!-- The card's id is the project's, so the board is the way into the
           project screen rather than a dead end beside it. -->
      <Link :href="`/auftraege/${card.id}`" class="underline-offset-2 hover:underline">
        {{ card.names }}
      </Link>
    </h3>

    <div class="mt-2 space-y-0.5 text-xs text-muted-foreground">
      <p class="tabular-nums">
        {{ dateLabel ?? $t('board.openDate') }}
        <template v-if="dateLabel"> · {{ $t('board.shootCount', card.shootCount) }}</template>
      </p>
      <p v-if="card.venue">{{ card.venue }}</p>
      <p>{{ card.source }}</p>
    </div>

    <p
      v-for="conflict in card.conflicts"
      :key="conflict.projectId"
      class="mt-2 rounded border border-destructive/30 bg-destructive/5 px-2 py-1 text-xs"
    >
      {{ $t('board.conflict', { project: conflict.projectName }) }}
    </p>

    <div class="mt-3 flex items-center justify-between">
      <span
        class="text-xs tabular-nums"
        :class="stale ? 'text-destructive' : 'text-muted-foreground'"
      >
        {{
          card.daysInStage === 0
            ? $t('board.today')
            : stale
              ? $t('board.stale', { count: card.daysInStage })
              : $t('board.daysInStage', card.daysInStage)
        }}
      </span>

      <!-- Hover only, per the clickdummy's card anatomy: the stage-advance
           control does not sit on every card. -->
      <button
        v-if="nextStage"
        type="button"
        class="text-xs underline opacity-0 transition group-hover:opacity-100 focus:opacity-100"
        @click="advance"
      >
        {{ $t('board.advance') }}
      </button>
    </div>
  </article>
</template>
