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
  <div class="flex items-center gap-4">
    <div>
      <!-- A Project exists from the moment an inquiry lands (vision §3.1), but
           calling an unbooked one "Projekt" while the board calls it an
           Anfrage is the same screen-to-screen disagreement the derived
           position exists to prevent. The word follows the commitment. -->
      <div class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">
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
    <span v-else class="ml-auto flex-none rounded-[5px] bg-ok-bg px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase text-ok">{{ $t('project.booked') }}</span>
  </div>

  <div class="mt-2.5 flex flex-row gap-6">
    <span class="inline-flex items-center bg-left bg-no-repeat bg-[length:13px_13px] pl-[19px] text-[12.5px] leading-[1.35] font-normal text-muted-foreground bg-icon-date">
      {{ dateLabel ? $t('project.mainDate', { date: dateLabel }) : $t('board.openDate') }}
    </span>
    <span class="inline-flex items-center bg-left bg-no-repeat bg-[length:13px_13px] pl-[19px] text-[12.5px] leading-[1.35] font-normal text-muted-foreground bg-icon-kind">{{ $t('board.shootCount', header.shootCount) }}</span>
  </div>

  <!-- Derived, never stored: the least-advanced uncancelled shoot speaks for
       the project, and the lead stage answers when none exists. -->
  <div class="mt-4 flex items-center gap-2.5">
    <span class="inline-flex items-center gap-[7px] rounded-[5px] bg-ok-bg px-[11px] py-1.5 text-[12px] leading-none font-semibold text-ok"><span class="size-[7px] flex-none rounded-full bg-ok" />{{ header.position.label }}</span>
    <span class="text-[12px] leading-none font-normal text-faint">{{ $t(`project.positionSource.${header.position.source}`) }}</span>
  </div>

  <div class="mt-4 mb-6 flex gap-6">
    <Link
      v-for="tab in header.tabs"
      :key="tab.key"
      :href="tab.href"
      class="text-[13px] leading-none no-underline"
      :class="tab.key === active ? 'font-semibold text-foreground' : 'font-normal text-muted-foreground'"
    >
      {{ tab.label }}
    </Link>
  </div>
</template>
