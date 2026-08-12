<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'

const props = defineProps<{ header: App.Crm.Data.ProjectHeaderData; active: string }>()

const dateLabel = computed(() =>
  props.header.primaryDate ? new Date(props.header.primaryDate).toLocaleDateString('de-DE') : null,
)

// Mockup 04's header states the position and offers the transition that
// advances it — nothing else. There is no field here to type a status into,
// which is the whole point of deriving it.
function book() {
  router.post(`/auftraege/${props.header.id}/buchung`, {}, { preserveScroll: true })
}
</script>

<template>
  <div class="hrow">
    <div>
      <!-- A Project exists from the moment an inquiry lands (vision §3.1), but
           calling an unbooked one "Projekt" while the board calls it an
           Anfrage is the same screen-to-screen disagreement the derived
           position exists to prevent. The word follows the commitment. -->
      <div class="kick">
        {{ $t(header.isBooked ? 'project.kicker' : 'project.kickerLead', { type: header.type }) }}
      </div>
      <h1>{{ header.names }}</h1>
    </div>

    <AlertDialog v-if="!header.isBooked && header.canBook">
      <AlertDialogTrigger as-child>
        <Button type="button">
          <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
          {{ $t('project.book') }}
        </Button>
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{{ $t('project.bookConfirm') }}</AlertDialogTitle>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogAction @click="book">{{ $t('project.book') }}</AlertDialogAction>
          <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
    <span v-else class="chip done" style="margin-left: auto">{{ $t('project.booked') }}</span>
  </div>

  <div class="meta row">
    <span class="d">
      {{ dateLabel ? $t('project.mainDate', { date: dateLabel }) : $t('board.openDate') }}
    </span>
    <span class="q">{{ $t('board.shootCount', header.shootCount) }}</span>
  </div>

  <!-- Derived, never stored: the least-advanced uncancelled shoot speaks for
       the project, and the lead stage answers when none exists. -->
  <div class="stage">
    <span class="now"><span class="dot" />{{ header.position.label }}</span>
    <span class="next">{{ $t(`project.positionSource.${header.position.source}`) }}</span>
  </div>

  <div class="tabs">
    <Link
      v-for="tab in header.tabs"
      :key="tab.key"
      :href="tab.href"
      :class="{ on: tab.key === active }"
    >
      {{ tab.label }}
    </Link>
  </div>
</template>
