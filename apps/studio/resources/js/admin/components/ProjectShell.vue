<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import Page from '@/ui/page/Page.vue'
import RecordNav from '@/admin/components/RecordNav.vue'
import { Button } from '@/ui/button'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'

/*
 * The frame around every project screen: the tools on the left, the project's
 * own name over the work, and the one transition that moves it forward.
 *
 * The head belongs to the project, never to the tool — the name stands still
 * while the column moves. Which tool is open is the row that is lit, so the
 * heading never has to say it twice.
 */
const props = defineProps<{ header: App.Crm.Data.ProjectHeaderData; active: string }>()

function book() {
  router.post(`/auftraege/${props.header.id}/buchung`, {}, { preserveScroll: true })
}
</script>

<template>
  <Page data-tokens>
    <!-- Die Spalte lebt jetzt in RecordNav: Projekt und Galerie tragen
         dieselbe, damit sie nicht auseinanderlaufen. Das Projekt füllt
         keinen Slot — es hat kein Gesicht und keine Untergliederung. -->
    <template #left>
      <RecordNav :tabs="header.tabs" :active="active" />
    </template>

    <template v-if="$slots.rail" #right>
      <slot name="rail" />
    </template>

    <div class="min-w-0 flex-1 px-[26px] pt-5 pb-7 max-[640px]:px-5">
      <div class="text-[11px] tracking-[0.05em] uppercase text-ink-2">
        <Link href="/projekte">{{ $t('projects.title') }}</Link>
      </div>

      <div class="sticky top-0 z-[5] mt-0.5 flex items-center gap-3 bg-paper">
        <slot name="title">
          <h1 class="m-0 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em] max-[640px]:text-[25px]">
            {{ header.names }}
          </h1>
        </slot>

        <span
          v-if="header.isBooked"
          class="flex-none rounded-[var(--r-sm)] bg-ok-bg px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase text-ok"
        >{{ $t('project.booked') }}</span>

        <div class="ml-auto flex items-center gap-2">
          <slot name="actions" />

          <AlertDialog v-if="!header.isBooked && header.canBook">
            <AlertDialogTrigger as-child>
              <Button variant="pill" type="button">{{ $t('project.book') }}</Button>
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
        </div>
      </div>

      <div class="mt-5">
        <slot />
      </div>
    </div>
  </Page>
</template>
