<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { Button } from '@/ui/button'

const props = defineProps<{ header: App.Crm.Data.ProjectHeaderData; active: string }>()

const { t } = useI18n()

const dateLabel = computed(() =>
  props.header.primaryDate ? new Date(props.header.primaryDate).toLocaleDateString('de-DE') : null,
)

// Every status on this screen is derived from a row. The header states where
// the project stands and offers no way to type it — the only way to move it is
// to move a shoot.
function book() {
  if (confirm(t('project.bookConfirm'))) {
    router.post(`/auftraege/${props.header.id}/buchung`, {}, { preserveScroll: true })
  }
}
</script>

<template>
  <header class="space-y-4">
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-[11px] uppercase tracking-wide text-muted-foreground">
          {{ $t('project.kicker', { type: header.type }) }}
        </p>
        <h1 class="text-2xl font-semibold tracking-tight">{{ header.names }}</h1>
      </div>

      <Button v-if="!header.isBooked" @click="book">{{ $t('project.book') }}</Button>
      <span v-else class="rounded border px-2 py-1 text-xs text-muted-foreground">
        {{ $t('project.booked') }}
      </span>
    </div>

    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
      <span class="tabular-nums">
        {{ dateLabel ? $t('project.mainDate', { date: dateLabel }) : $t('board.openDate') }}
      </span>
      <span>{{ $t('board.shootCount', header.shootCount) }}</span>
    </div>

    <!-- Derived, never stored: the least-advanced uncancelled shoot speaks for
         the project, and the lead stage answers when none exists. -->
    <div class="flex items-center gap-2 rounded-lg border px-3 py-2">
      <span class="size-1.5 rounded-full bg-foreground" />
      <span class="text-sm font-medium">{{ header.position.label }}</span>
      <span class="text-xs text-muted-foreground">
        {{ $t(`project.positionSource.${header.position.source}`) }}
      </span>
    </div>

    <nav class="flex gap-4 border-b text-sm">
      <Link
        v-for="tab in header.tabs"
        :key="tab.key"
        :href="tab.href"
        class="-mb-px border-b-2 pb-2"
        :class="tab.key === active ? 'border-foreground font-medium' : 'border-transparent text-muted-foreground'"
      >
        {{ tab.label }}
      </Link>
    </nav>
  </header>
</template>
