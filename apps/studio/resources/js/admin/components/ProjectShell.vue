<script setup lang="ts">
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import Page from '@/ui/page/Page.vue'
import ProjectQueue from '@/admin/components/ProjectQueue.vue'
import StageTrack from '@/admin/components/StageTrack.vue'
import RailHistory from '@/admin/components/RailHistory.vue'
import Rail from '@/ui/page/Rail.vue'
import RailItem from '@/ui/page/RailItem.vue'
import RailSection from '@/ui/page/RailSection.vue'
import { formatEurosRounded } from '@/admin/lib/money'
import { dayWithWeekday as day } from '@/admin/lib/dates'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'

/*
 * The frame around every project screen: the other projects on the left, the
 * project's own name, its way from inquiry to delivery and its tools over the
 * work, and the one transition that moves it forward.
 *
 * The head belongs to the project, never to the tool — the name and the track
 * stand still while the tabs change. Which tool is open is the tab that is
 * lit, so the heading never has to say it twice.
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

function toggleReminders(value: boolean | 'indeterminate') {
  router.patch(
    `/zahlungsplaene/${props.header.paymentPlan?.id}/erinnerungen`,
    { client_reminders_enabled: value === true },
    { preserveScroll: true },
  )
}

function destroy() {
  router.delete(`/auftraege/${props.header.id}`)
}
</script>

<template>
  <Page data-tokens>
    <template #left>
      <slot name="left">
        <ProjectQueue :groups="header.queue" />
      </slot>
    </template>

    <template #right>
      <slot name="rail">
        <Rail>
          <slot name="people">
            <RailSection :title="$t('lead.people')">
              <div v-for="person in header.people" :key="person.id" class="flex items-center gap-2 py-[5px] text-[12.5px] leading-[1.47]">
                <Link :href="`/kontakte/${person.id}`">{{ person.name }}</Link>
                <span class="ml-auto text-[11.5px] leading-[1.47] text-[#3F6B4A]">{{ $t(`contacts.role.${person.role}`) }}</span>
              </div>
            </RailSection>
          </slot>

          <RailSection v-if="header.paymentPlan" :title="$t('dashboard.payments')">
            <div v-for="instalment in header.paymentPlan.installments" :key="instalment.id" class="flex gap-2.5 py-[5px] text-[12.5px] leading-[1.47]">
              <span class="w-[74px] flex-none text-ink-2">{{ day(instalment.dueOn) }}</span>
              <span>{{ formatEurosRounded(Number(instalment.amount)) }}</span>
            </div>

            <div class="mt-2 flex items-center gap-2">
              <Checkbox id="cb-plan-reminders" :model-value="header.paymentPlan.clientRemindersEnabled" @update:model-value="toggleReminders" />
              <Label variant="inline" for="cb-plan-reminders">{{ $t('payments.clientReminders') }}</Label>
            </div>
          </RailSection>

          <RailSection v-if="header.galleries.length" :title="$t('contacts.galleries')">
            <RailItem
              v-for="gallery in header.galleries"
              :key="gallery.id"
              :title="gallery.title ?? '—'"
              :href="`/galerien/${gallery.id}`"
              :meta="gallery.expiresAt ? $t('contacts.expiresOn', { date: day(gallery.expiresAt) }) : null"
            />
          </RailSection>

          <RailHistory :entries="header.history" />
        </Rail>
      </slot>
    </template>

    <div class="min-w-0 flex-1 px-[26px] pt-5 pb-7 max-[640px]:px-5">
      <div class="text-[11px] tracking-[0.05em] uppercase text-ink-2">
        <Link v-if="header.isBooked" href="/projekte">{{ $t('projects.title') }}</Link>
        <Link v-else href="/anfragen">{{ $t('board.title') }}</Link>
      </div>

      <div class="sticky top-0 z-[5] mt-0.5 bg-paper">
        <div class="flex items-center gap-3">
          <slot name="title">
            <h1 class="m-0 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em] max-[640px]:text-[25px]">
              {{ header.name }}
            </h1>
          </slot>

          <div class="ml-auto flex items-center gap-2">
            <slot name="actions" />

            <!-- A page with a stage menu books through it. -->
            <AlertDialog v-if="!header.isBooked && header.canBook && !$slots.menu">
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

            <DropdownMenu v-if="$slots.menu || (header.canClose && !header.outcomeLabel) || header.canDelete">
              <DropdownMenuTrigger
                class="flex size-8 flex-none cursor-pointer items-center justify-center rounded-[var(--r)] bg-sunk-2"
                :aria-label="$t('project.more')"
              >
                <svg class="size-4 fill-current" viewBox="0 0 24 24">
                  <circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" />
                </svg>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-[208px]">
                <slot name="menu" />
                <DropdownMenuItem
                  v-if="header.canClose && !header.outcomeLabel"
                  :class="{ 'border-t border-rule': $slots.menu }"
                  @select="closing = true"
                >
                  {{ $t('project.closeMenu') }}
                </DropdownMenuItem>
                <DropdownMenuItem v-if="header.canDelete" class="text-destructive" @select="deleting = true">
                  {{ $t('project.deleteMenu') }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        <StageTrack class="mt-4" :steps="header.track" />

        <nav class="mt-[18px] flex gap-[18px] overflow-x-auto text-[13px] shadow-[inset_0_-1px_0_var(--rule)]">
          <Link
            v-for="tab in header.tabs"
            :key="tab.key"
            :href="tab.href"
            class="pb-2 whitespace-nowrap no-underline"
            :class="tab.key === active
              ? 'font-semibold text-foreground shadow-[inset_0_-2px_0_var(--ink)]'
              : 'text-ink-2 hover:text-foreground'"
            :aria-current="tab.key === active ? 'page' : undefined"
          >
            {{ $t(tab.label) }}<span v-if="tab.count" class="ml-1 text-[11.5px]">{{ tab.count }}</span>
          </Link>
        </nav>
      </div>

      <div
        v-if="header.outcomeLabel"
        class="mt-2.5 flex items-center gap-3 rounded-[7px] bg-late-bg py-[7px] pr-[9px] pl-[11px] text-[12.5px] leading-[1.3] font-medium text-destructive"
      >
        {{ header.outcomeLabel }} — {{ header.lostReason }}
        <Button v-if="header.canClose" class="ml-auto" variant="outline" type="button" @click="reopen">
          {{ $t('lead.reopen') }}
        </Button>
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
