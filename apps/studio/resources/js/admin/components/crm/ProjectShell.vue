<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import Page from '@/ui/page/Page.vue'
import ProjectQueue from '@/admin/components/crm/ProjectQueue.vue'
import StageTrack from '@/admin/components/crm/StageTrack.vue'
import RailHistory from '@/admin/components/crm/RailHistory.vue'
import SettlePlanDialog from '@/admin/components/payments/SettlePlanDialog.vue'
import Rail from '@/ui/page/Rail.vue'
import RailItem from '@/ui/page/RailItem.vue'
import RailSection from '@/ui/page/RailSection.vue'
import { formatEurosRounded } from '@/lib/money'
import { dayWithWeekday as day } from '@/admin/lib/dates'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Switch } from '@/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { AlertDialog, AlertDialogAction, AlertDialogBody, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'

/*
 * The frame around every project screen: the other projects on the left, the
 * project's own name, its way from inquiry to delivery and its tools over the
 * work, and the one transition that moves it forward.
 *
 * The head belongs to the project, never to the tool — the name and the track
 * stand still while the tabs change. Which tool is open is the tab that is
 * lit, so the heading never has to say it twice.
 */
const props = withDefaults(
  defineProps<{ header: App.Crm.Screens.CaseHeaderData; active: string; child?: string; people?: boolean }>(),
  { people: true },
)

const subTabs = computed(() => props.header.tabs.find((tab) => tab.key === props.active)?.children ?? [])

function book() {
  router.post(`/auftraege/${props.header.id}/buchung`, {}, { preserveScroll: true })
}

function togglePortal() {
  router.post(`/auftraege/${props.header.id}/portal-sichtbarkeit`, {}, { preserveScroll: true })
}

const closing = ref(false)
const deleting = ref(false)
const closeForm = useForm({ outcome: 'lost', reason: '' })

function close() {
  closeForm.post(`/anfragen/${props.header.id}/schliessen`, {
    preserveScroll: true,
    onSuccess: () => {
      closing.value = false
      closeForm.reset()
    },
  })
}

function reopen() {
  router.post(`/anfragen/${props.header.id}/wieder-oeffnen`, {}, { preserveScroll: true })
}

function complete() {
  router.post(`/auftraege/${props.header.id}/abschluss`, {}, { preserveScroll: true })
}

function reopenProject() {
  router.delete(`/auftraege/${props.header.id}/abschluss`, { preserveScroll: true })
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
            <RailSection v-if="people" :title="$t('lead.people')">
              <div v-for="person in header.people" :key="person.id" class="flex items-center gap-2 py-[5px] text-small leading-[1.47]">
                <Link :href="`/kontakte/${person.id}`">{{ person.name }}</Link>
                <span class="ml-auto text-small leading-[1.47] text-ok">{{ $t(`contacts.role.${person.role}`) }}</span>
              </div>
            </RailSection>
          </slot>

          <RailSection v-if="header.paymentPlan" :title="$t('dashboard.payments')">
            <div v-for="instalment in header.paymentPlan.installments" :key="instalment.id" class="flex gap-2.5 py-[5px] text-small leading-[1.47]">
              <span class="w-[74px] flex-none text-ink-2">{{ day(instalment.dueOn) }}</span>
              <span>{{ formatEurosRounded(Number(instalment.amount)) }}</span>
            </div>

            <SettlePlanDialog
              v-if="header.canManagePaymentPlan && header.primaryShootCancelled && !header.paymentPlan.settledOn"
              class="mt-2"
              :plan="header.paymentPlan"
            />
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
      <div class="text-small font-medium text-ink-2">
        <Link v-if="header.isBooked" href="/projekte">{{ $t('projects.title') }}</Link>
        <Link v-else href="/anfragen">{{ $t('board.title') }}</Link>
      </div>

      <div class="sticky top-0 z-[5] -mx-[26px] mt-0.5 bg-background px-[26px] pb-4 max-[640px]:-mx-5 max-[640px]:px-5">
        <div class="flex flex-wrap items-center gap-3">
          <slot name="title">
            <h1 class="m-0 text-[20px] leading-7 font-semibold tracking-[-0.02em]">
              {{ header.name }}
            </h1>
          </slot>

          <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
            <slot name="actions" />
            <slot name="stage" />

            <!-- A page with a stage menu books through it. -->
            <AlertDialog v-if="!header.isBooked && header.canBook && !$slots.stage">
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

            <div v-if="header.canManagePortalVisibility" class="flex items-center gap-2">
              <Label for="portal-visible" variant="inline">{{ $t('project.portal') }}</Label>
              <Switch id="portal-visible" :model-value="header.portalVisible" @update:model-value="togglePortal" />
            </div>

            <DropdownMenu v-if="(header.canComplete && !header.isCompleted) || (header.canClose && !header.outcomeLabel) || header.canDelete">
              <DropdownMenuTrigger
                class="flex size-8 flex-none cursor-pointer items-center justify-center rounded-[var(--r)] bg-sunk-2"
                :aria-label="$t('project.more')"
              >
                <svg class="size-4 fill-current" viewBox="0 0 24 24">
                  <circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" />
                </svg>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-[208px]">
                <DropdownMenuItem v-if="header.canComplete && !header.isCompleted" @select="complete">
                  {{ $t('project.completeMenu') }}
                </DropdownMenuItem>
                <DropdownMenuItem
                  v-if="header.canClose && !header.outcomeLabel"
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

        <!-- The track scrolls once the tabs outgrow the column; a local
             background paints its whole scroll width, not only what shows. -->
        <nav class="mt-4 inline-flex max-w-full gap-0.5 overflow-x-auto rounded-lg p-[3px] text-small font-medium [background:linear-gradient(var(--sunk),var(--sunk))_local] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <Link
            v-for="tab in header.tabs"
            :key="tab.key"
            :href="tab.href"
            class="rounded-[var(--r-sm)] px-2.5 py-1 whitespace-nowrap no-underline"
            :class="tab.key === active
              ? 'bg-paper text-foreground shadow-[0_1px_2px_rgba(24,24,27,.08)]'
              : 'text-ink-2 hover:text-foreground'"
            :aria-current="tab.key === active ? 'page' : undefined"
          >
            {{ $t(tab.label) }}<span v-if="tab.count" class="ml-1 text-small">{{ tab.count }}</span>
          </Link>
        </nav>

        <nav
          v-if="subTabs.length"
          class="flex gap-[18px] pt-3 text-small"
        >
          <Link
            v-for="entry in subTabs"
            :key="entry.key"
            :href="entry.href"
            class="whitespace-nowrap no-underline"
            :class="entry.key === child ? 'font-semibold text-foreground' : 'text-ink-2 hover:text-foreground'"
            :aria-current="entry.key === child ? 'page' : undefined"
          >
            {{ $t(entry.label) }}<span v-if="entry.count" class="ml-1 text-small">{{ entry.count }}</span>
          </Link>
        </nav>
      </div>

      <div
        v-if="header.outcomeLabel"
        class="mt-2.5 flex items-center gap-3 rounded-[7px] bg-late-bg py-[7px] pr-[9px] pl-[11px] text-small leading-[1.3] font-medium text-destructive"
      >
        {{ header.outcomeLabel }} — {{ header.lostReason }}
        <Button v-if="header.canClose" class="ml-auto" variant="outline" type="button" @click="reopen">
          {{ $t('lead.reopen') }}
        </Button>
      </div>

      <div
        v-if="header.isCompleted"
        class="mt-2.5 flex items-center gap-3 rounded-[7px] bg-sunk-2 py-[7px] pr-[9px] pl-[11px] text-small leading-[1.3] font-medium"
      >
        {{ $t('project.completed') }}
        <Button v-if="header.canComplete" class="ml-auto" variant="outline" type="button" @click="reopenProject">
          {{ $t('lead.reopen') }}
        </Button>
      </div>

      <AlertDialog v-model:open="closing">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{{ $t('project.closeTitle') }}</AlertDialogTitle>
          </AlertDialogHeader>
          <AlertDialogBody>
            <Field v-slot="{ id }" :label="$t('board.outcome')">
              <Select v-model="closeForm.outcome">
                <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="lost">{{ $t(header.isBooked ? 'board.outcomeCancelled' : 'board.outcomeLost') }}</SelectItem>
                  <SelectItem value="declined">{{ $t('board.outcomeDeclined') }}</SelectItem>
                  <SelectItem value="closed">{{ $t('board.outcomeClosed') }}</SelectItem>
                </SelectContent>
              </Select>
            </Field>
            <Field v-slot="{ id }" :label="$t('board.closeReason')" :error="closeForm.errors.reason">
              <Input :id="id" v-model="closeForm.reason" type="text" />
            </Field>
          </AlertDialogBody>
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
            <AlertDialogAction tone="destructive" @click="destroy">{{ $t('common.delete') }}</AlertDialogAction>
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
