<script setup lang="ts">
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import Page from '@/ui/page/Page.vue'
import RecordNav from '@/admin/components/RecordNav.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
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

const closing = ref(false)
const deleting = ref(false)
const closeForm = useForm({ outcome: 'lost', reason: '' })

function close() {
  closeForm.post(`/anfragen/${props.header.leadId}/schliessen`, {
    preserveScroll: true,
    onSuccess: () => {
      closing.value = false
      closeForm.reset()
    },
  })
}

function reopen() {
  router.post(`/anfragen/${props.header.leadId}/wieder-oeffnen`, {}, { preserveScroll: true })
}

function destroy() {
  router.delete(`/auftraege/${props.header.id}`)
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
            {{ header.name }}
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

          <DropdownMenu v-if="(header.canClose && !header.outcomeLabel) || header.canDelete">
            <DropdownMenuTrigger
              class="flex size-8 flex-none cursor-pointer items-center justify-center rounded-[var(--r)] bg-sunk-2"
              :aria-label="$t('project.more')"
            >
              <svg class="size-4 fill-current" viewBox="0 0 24 24">
                <circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" />
              </svg>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-[200px]">
              <DropdownMenuItem v-if="header.canClose && !header.outcomeLabel" @select="closing = true">
                {{ $t('project.closeMenu') }}
              </DropdownMenuItem>
              <DropdownMenuItem v-if="header.canDelete" class="text-destructive" @select="deleting = true">
                {{ $t('project.deleteMenu') }}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <div
        v-if="header.outcomeLabel"
        class="mt-2.5 flex items-center gap-3 rounded-[7px] bg-late-bg py-[7px] pr-[9px] pl-[11px] text-[12.5px] leading-[1.3] font-medium text-destructive"
      >
        {{ header.outcomeLabel }} — {{ header.lostReason }}
        <button
          v-if="header.canClose"
          class="ml-auto cursor-pointer whitespace-nowrap text-[var(--accent)]"
          type="button"
          @click="reopen"
        >
          {{ $t('lead.reopen') }}
        </button>
      </div>

      <AlertDialog v-model:open="closing">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{{ $t('project.closeTitle') }}</AlertDialogTitle>
          </AlertDialogHeader>
          <Field v-slot="{ id }" :label="$t('board.outcome')">
            <Select v-model="closeForm.outcome">
              <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="lost">{{ $t('board.outcomeLost') }}</SelectItem>
                <SelectItem value="declined">{{ $t('board.outcomeDeclined') }}</SelectItem>
                <SelectItem value="closed">{{ $t('board.outcomeClosed') }}</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <Field v-slot="{ id }" :label="$t('board.closeReason')" :error="closeForm.errors.reason">
            <Input :id="id" v-model="closeForm.reason" type="text" />
          </Field>
          <AlertDialogFooter>
            <AlertDialogAction :disabled="!closeForm.reason || closeForm.processing" @click.prevent="close">
              {{ $t('board.close') }}
            </AlertDialogAction>
            <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog v-model:open="deleting">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{{ $t('project.deleteTitle') }}</AlertDialogTitle>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogAction class="bg-destructive" @click="destroy">{{ $t('common.delete') }}</AlertDialogAction>
            <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <div class="mt-5">
        <slot />
      </div>
    </div>
  </Page>
</template>
