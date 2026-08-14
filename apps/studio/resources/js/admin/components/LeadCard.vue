<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Button } from '@/ui/button'

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
  <!-- The card's id is the project's, so the board is the way into the project
       screen rather than a dead end beside it. -->
  <Link class="card" :href="`/auftraege/${card.id}`">
    <div class="kick">{{ card.type }}</div>
    <h3 class="who">{{ card.names }}</h3>

    <div class="meta">
      <span class="d">
        {{ dateLabel ?? $t('board.openDate') }}
        <template v-if="dateLabel"> · {{ $t('board.shootCount', card.shootCount) }}</template>
      </span>
      <span v-if="card.venue" class="v">{{ card.venue }}</span>
      <span class="q">{{ card.source }}</span>
    </div>

    <div
      v-for="conflict in card.conflicts"
      :key="conflict.projectId ?? conflict.personId ?? conflict.label ?? undefined"
      class="flag"
    >
      {{
        conflict.source === 'closure'
          ? $t('board.conflictClosure', { label: conflict.label })
          : conflict.source === 'absence'
            ? $t('board.conflictAbsence', { label: conflict.label })
            : $t('board.conflict', { project: conflict.projectName })
      }}
    </div>

    <div class="foot">
      <span class="age" :class="{ warn: stale }">
        {{
          card.daysInStage === 0
            ? $t('board.today')
            : stale
              ? $t('board.stale', { count: card.daysInStage })
              : $t('board.daysInStage', card.daysInStage)
        }}
      </span>
    </div>

    <!-- Hover only, per the clickdummy's card anatomy: the stage-advance
         control does not sit on every card. -->
    <!-- .go is a 15px hover affordance drawn entirely by tokens.css; ghost is
         the reset it needs, not a style it takes -->
    <Button
      v-if="nextStage"
      variant="ghost"
      class="go"
      type="button"
      :aria-label="$t('board.advance')"
      @click.prevent.stop="advance"
    />
  </Link>
</template>
