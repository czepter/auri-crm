<script setup lang="ts">
import { computed, onBeforeUpdate, onMounted, ref, useSlots, useTemplateRef } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import Page from '@/ui/page/Page.vue'
import ProjectQueue from '@/admin/components/crm/ProjectQueue.vue'
import { usePageCommands } from '@/admin/lib/paletteCommands'
import { useI18n } from 'vue-i18n'
import StageTrack from '@/admin/components/crm/StageTrack.vue'
import SettlePlanDialog from '@/admin/components/payments/SettlePlanDialog.vue'
import { Ellipsis } from 'lucide-vue-next'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Switch } from '@/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { HoldToConfirm } from '@/ui/hold-to-confirm'
import { SlidingIndicator, useSlidingIndicator } from '@/ui/sliding-indicator'
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
const props = defineProps<{ header: App.Crm.Screens.CaseHeaderData; active: string }>()

const tabNav = useTemplateRef<HTMLElement>('tabNav')
// Fonts change the tab widths; centering before they load clips the last tab.
onMounted(() => document.fonts.ready.then(() => tabNav.value?.querySelector('[aria-current]')?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'instant' })))

// An inquiry has no tab of its own for shoots, tasks or notes; they sit under Übersicht.
const lit = computed(() => (props.header.tabs.some((tab) => tab.key === props.active) ? props.active : 'overview'))

const { rect: tabRect, animate: tabAnimate } = useSlidingIndicator(tabNav, () => lit.value, { selector: '[aria-current]', key: 'project-tabs' })

const booking = ref(false)

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

const { t } = useI18n()
const slots = useSlots()
const staged = ref(!!slots.stage)
onBeforeUpdate(() => (staged.value = !!slots.stage))

usePageCommands(() => {
  const { nextAction, canBook, isBooked, canComplete, isCompleted } = props.header

  return [
    ...(!staged.value && nextAction
      ? [{ id: 'next', title: t(nextAction.label), ...(nextAction.href ? { href: nextAction.href } : { run: () => router.post(nextAction.post!, {}, { preserveScroll: true }) }) }]
      : []),
    ...(!staged.value && !isBooked && canBook ? [{ id: 'book', title: t('project.book'), run: () => (booking.value = true) }] : []),
    ...(canComplete && !isCompleted ? [{ id: 'complete', title: t('project.completeMenu'), run: complete }] : []),
  ]
})
</script>

<template>
  <Page data-tokens>
    <template #left>
      <slot name="left">
        <ProjectQueue :groups="header.queue" />
      </slot>
    </template>

    <div class="min-w-0 flex-1 px-[26px] pt-5 pb-7 max-[640px]:px-4">
      <div class="text-small font-medium text-ink-2">
        <Link v-if="header.isBooked" href="/projekte">{{ $t('projects.title') }}</Link>
        <Link v-else href="/anfragen">{{ $t('board.title') }}</Link>
      </div>

      <div class="sticky top-0 z-[5] -mx-[26px] mt-0.5 bg-background px-[26px] pb-4 max-[640px]:contents">
        <div class="flex flex-wrap items-center gap-3">
          <slot name="title">
            <h1 class="m-0 text-title leading-7 font-semibold tracking-[-0.02em]">
              {{ header.name }}
            </h1>
          </slot>

          <div class="ml-auto flex flex-wrap items-center justify-end gap-2 max-[640px]:w-full max-[640px]:justify-between">
            <slot name="actions" />
            <slot name="stage" />

            <template v-if="!$slots.stage && header.nextAction">
              <Button v-if="header.nextAction.href" variant="default" as-child class="max-[640px]:min-h-11">
                <Link :href="header.nextAction.href">{{ $t(header.nextAction.label) }}</Link>
              </Button>
              <Button v-else variant="default" type="button" class="max-[640px]:min-h-11" @click="router.post(header.nextAction.post!, {}, { preserveScroll: true })">
                {{ $t(header.nextAction.label) }}
              </Button>
            </template>

            <!-- A page with a stage menu books through it. -->
            <AlertDialog v-if="!header.isBooked && header.canBook && !$slots.stage" v-model:open="booking">
              <AlertDialogTrigger as-child>
                <Button :variant="$slots.actions || header.nextAction || active !== 'overview' ? 'outline' : 'default'" type="button">{{ $t('project.book') }}</Button>
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

            <div v-if="header.canManagePortalVisibility" class="flex items-center gap-2 max-[640px]:hidden">
              <Label for="portal-visible" variant="inline">{{ $t('project.portal') }}</Label>
              <Switch id="portal-visible" :model-value="header.portalVisible" @update:model-value="togglePortal" />
            </div>

            <DropdownMenu v-if="$slots.menu || header.canGenerateContract || (header.canComplete && !header.isCompleted) || (header.canClose && !header.outcomeLabel) || header.canDelete || header.canManagePortalVisibility">
              <DropdownMenuTrigger as-child>
                <Button variant="secondary" size="icon" type="button" class="max-[640px]:min-h-11 max-[640px]:min-w-11" :aria-label="$t('project.more')">
                  <Ellipsis class="size-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-[208px]">
                <slot name="menu" />
                <DropdownMenuItem v-if="header.canGenerateContract" as-child>
                  <Link :href="`/auftraege/${header.id}/vertrag`">{{ $t('project.contractGenerate') }}</Link>
                </DropdownMenuItem>
                <DropdownMenuItem v-if="header.canManagePortalVisibility" class="min-[641px]:hidden" @select="togglePortal">
                  {{ $t(header.portalVisible ? 'project.portalHide' : 'project.portalShow') }}
                </DropdownMenuItem>
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
        <div class="mt-4 max-[640px]:sticky max-[640px]:top-0 max-[640px]:z-[5] max-[640px]:-mx-4 max-[640px]:mt-2 max-[640px]:bg-background max-[640px]:px-4 max-[640px]:py-2">
        <nav ref="tabNav" class="relative flex max-w-full overflow-x-auto text-small font-medium border-b border-rule [scrollbar-width:none] [&::-webkit-scrollbar]:hidden max-[640px]:[mask-image:linear-gradient(to_right,#000_calc(100%-28px),transparent)] max-[640px]:after:w-6 max-[640px]:after:flex-none max-[640px]:after:content-['']">
          <SlidingIndicator
            :rect="tabRect"
            :animate="tabAnimate"
            class="[background:linear-gradient(var(--ink),var(--ink))_bottom/100%_2px_no-repeat]"
          />
          <Link
            v-for="tab in header.tabs"
            :key="tab.key"
            :href="tab.href"
            class="relative inline-flex min-h-10 flex-none items-center px-3 whitespace-nowrap no-underline"
            :class="[
              tab.key === lit
                ? ['font-semibold text-foreground', !tabRect && '[background:linear-gradient(var(--ink),var(--ink))_bottom/100%_2px_no-repeat]']
                : 'text-ink-2 hover:text-foreground',
            ]"
            :aria-current="tab.key === lit ? 'page' : undefined"
          >
            {{ $t(tab.label) }}<span v-if="tab.count" class="ml-1 text-small">{{ tab.count }}</span>
          </Link>
        </nav>
        </div>
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

      <SettlePlanDialog
        v-if="header.paymentPlan && header.canManagePaymentPlan && header.primaryShootCancelled && !header.paymentPlan.settledOn"
        class="mt-2.5"
        :plan="header.paymentPlan"
      />

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
            <AlertDialogAction :disabled="closeForm.processing" @click.prevent="close">
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
            <HoldToConfirm tone="destructive" @confirm="deleting = false; destroy()">{{ $t('common.delete') }}</HoldToConfirm>
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
