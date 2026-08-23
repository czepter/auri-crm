<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import Page from '@/ui/page/Page.vue'
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
    <!-- The tools of one project, a column rather than a strip over the
         content: six entries with a count beside each need a left edge to
         align on. Below 640px it lies down and scrolls sideways —
         navigation, unlike the rail, never disappears when the screen
         narrows. -->
    <template #left>
      <nav
        class="flex w-[150px] flex-none flex-col gap-0.5 border-r border-border px-2.5 py-4 max-[640px]:w-full max-[640px]:flex-row max-[640px]:self-start max-[640px]:overflow-x-auto max-[640px]:border-r-0 max-[640px]:border-b max-[640px]:px-5 max-[640px]:py-2.5"
      >
        <Link
          v-for="tab in header.tabs"
          :key="tab.key"
          class="flex items-center gap-2.5 rounded-[7px] px-2.5 py-[7px] text-[13px] leading-none whitespace-nowrap no-underline transition-[background-color] duration-[.12s] ease-out hover:bg-muted"
          :class="
            tab.key === active
              ? 'bg-muted font-semibold text-foreground'
              : 'font-normal text-muted-foreground'
          "
          :href="tab.href"
        >
          {{ $t(tab.label) }}
          <span v-if="tab.count" class="ml-auto text-[11.5px] tabular-nums text-ink-2">{{ tab.count }}</span>
        </Link>
      </nav>
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
