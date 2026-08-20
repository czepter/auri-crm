<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/ui/alert-dialog'

type Card = App.Crm.Data.LeadCardData
type Column = { stage: string; label: string; cards: Card[]; total: number }
type Group = { key: string; rows: Card[] }

const props = defineProps<{ columns: Column[]; groups: Group[]; view: string }>()

const money = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })

const view = ref(props.view)

// The switch writes the URL without a navigation and stores the choice, so a
// link stays shareable and the next visit opens on the same one.
function choose(next: string) {
  view.value = next

  const url = new URL(window.location.href)
  url.searchParams.set('ansicht', next)
  history.replaceState(history.state, '', url)

  router.post('/anfragen/ansicht', { ansicht: next }, { preserveScroll: true, preserveState: true })
}

// Grey rather than faded: opacity would have pushed the text under the
// legibility floor, the same trap as a third grey.
const DONE_STAGES = ['contract_sent', 'booked']

function shortDate(date: string | null) {
  return date ? new Date(date).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) : null
}

function relative(at: string) {
  const days = Math.round((Date.now() - new Date(at).getTime()) / 86400000)

  return days < 1 ? 'heute' : new Intl.RelativeTimeFormat('de-DE', { numeric: 'auto' }).format(-days, 'day')
}

// Nothing travels with the pointer, so the row itself has to say it is under
// way — otherwise one drags something that visibly stays put. The insertion
// mark is the only thing that moves.
const moving = ref<{ id: string; leadId: string; column: number } | null>(null)
const target = ref<number | null>(null)
const insertAt = ref<number | null>(null)

function onDragStart(event: DragEvent, card: Card, column: number) {
  moving.value = { id: card.id, leadId: card.leadId, column }
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function onDragOver(event: DragEvent, column: number, row: number | null) {
  if (moving.value === null || moving.value.column === column) return

  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  target.value = column
  insertAt.value = row
}

function onDrop(column: number) {
  if (moving.value === null || moving.value.column === column) return

  move(moving.value.leadId, props.columns[column].stage)
  reset()
}

function reset() {
  moving.value = null
  target.value = null
  insertAt.value = null
}

// The move to "Gebucht" is the booking (AdvanceLeadStage): the job is
// written, the shoots go to booked and the card leaves this board for
// /projekte. Every other move is a stage correction and asks nothing.
const booking = ref<string | null>(null)

function move(leadId: string, stage: string) {
  if (stage === 'booked') {
    booking.value = leadId

    return
  }

  router.post(`/anfragen/${leadId}/stufe`, { stage }, { preserveScroll: true })
}

function book() {
  router.post(`/anfragen/${booking.value}/stufe`, { stage: 'booked' }, { preserveScroll: true })
  booking.value = null
}

// Without a mouse, and that is not optional: dragging a row across six
// columns is unusable on an iPad, and the table knows no dragging at all.
function onKey(event: KeyboardEvent, card: Card, column: number) {
  if (!event.metaKey && !event.ctrlKey) return

  const to = event.key === 'ArrowRight' ? column + 1 : event.key === 'ArrowLeft' ? column - 1 : null
  const stage = to === null ? null : props.columns[to]?.stage

  if (stage) {
    event.preventDefault()
    move(card.leadId, stage)
  }
}

// The same targets the arrows reach, for a pointer that has no keyboard
// beside it.
const menu = ref<{ card: Card; x: number; y: number } | null>(null)

function openMenu(event: MouseEvent, card: Card) {
  event.preventDefault()
  menu.value = { card, x: event.clientX, y: event.clientY }
}

const groupRows = computed(() => props.groups.filter((group) => group.rows.length))

/* crm.css .stufe s.s2 … s.s5 — the stage dot darkens toward the decision and
   goes warm at the one that still needs an answer. */
const STAGE_DOT = ['bg-foreground', 'bg-[#5F5F63]', 'bg-[#A1A1A6]', 'bg-action', 'bg-mark']
</script>

<template>
  <AdminLayout active="anfragen">
    <div data-tokens="crm" @click="menu = null">
      <div class="flex items-center gap-3 px-8 pt-[22px]">
        <h1 class="mt-0.5 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em]">{{ $t('board.title') }}</h1>

        <span class="ml-auto flex rounded-lg bg-sunk-2 p-0.5">
          <button
            v-for="option in (['board', 'liste'] as const)"
            :key="option"
            class="cursor-pointer rounded-[6px] border-0 bg-transparent px-3.5 py-1 text-[12.5px] leading-[1.4] text-foreground transition-colors duration-[120ms] ease-out"
            :class="view === option ? 'bg-paper font-medium' : 'font-normal'"
            type="button"
            @click="choose(option)"
          >
            {{ option === 'board' ? $t('board.viewBoard') : $t('board.viewList') }}
          </button>
        </span>

        <Link
          class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border-0 bg-action px-3.5 py-1.5 text-[12.5px] font-medium tracking-[-0.01em] whitespace-nowrap text-white no-underline transition-[filter,background] duration-[120ms] ease-out hover:brightness-[1.08]"
          href="/anfragen/neu"
        >
          {{ $t('board.create') }}
        </Link>
      </div>

      <!-- Board — list columns, no cards. Grey cards on a white ground invert
           the material order, in which the container carries material and the
           content stays white. -->
      <div v-if="view === 'board'" class="flex items-stretch overflow-x-auto px-5 pt-5 pb-7">
        <section
          v-for="(column, index) in columns"
          :key="column.stage"
          class="flex min-w-0 shrink-0 grow basis-[190px] flex-col gap-2 px-3 [&:not(:first-child)]:shadow-[inset_1px_0_0_#F0F0F2]"
          :class="target === index && 'rounded-[12px] bg-sunk'"
          @dragover="onDragOver($event, index, null)"
          @drop.prevent="onDrop(index)"
        >
          <header class="flex items-baseline gap-1.5 pb-2">
            <b class="text-[11px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">{{ column.label }}</b>
            <span class="ml-auto text-[11px] tabular-nums text-ink-2">
              {{ column.cards.length }}<template v-if="column.total"> · {{ money.format(column.total / 100) }}</template>
            </span>
          </header>

          <template v-for="(card, row) in column.cards" :key="card.id">
            <div v-if="target === index && insertAt === row" class="-mx-1.5 h-0.5 rounded-[1px] bg-action" />

            <Link
              class="relative -mx-1.5 block cursor-grab rounded-lg border border-border px-1.5 py-[9px] text-inherit no-underline shadow-[inset_0_1px_0_#F0F0F2] transition-[background,border-color] duration-[120ms] ease-out first-of-type:shadow-none hover:bg-sunk"
              :class="moving?.id === card.id && 'cursor-grabbing'"
              :href="`/anfragen/${card.leadId}`"
              draggable="true"
              @dragstart="onDragStart($event, card, index)"
              @dragend="reset"
              @dragover="onDragOver($event, index, row)"
              @keydown="onKey($event, card, index)"
              @contextmenu="openMenu($event, card)"
            >
              <span
                class="text-[13.5px] leading-[1.3] tracking-[-0.014em]"
                :class="DONE_STAGES.includes(column.stage) || moving?.id === card.id
                  ? 'font-medium text-ink-2'
                  : 'font-semibold'"
              >
                {{ card.names }}
              </span>
              <span class="mt-0.5 text-[12px]" :class="moving?.id === card.id ? 'text-mark' : 'text-ink-2'">
                {{ shortDate(card.date) ?? $t('board.openDate') }}
                <template v-if="card.venue"> · {{ card.venue }}</template>
              </span>
              <!-- On both affected rows and without naming anyone: who
                   collides is the other marked row in the same picture. -->
              <span
                v-if="card.conflicts.length"
                class="mt-[3px] text-[11.5px]"
                :class="moving?.id === card.id ? 'text-mark' : 'text-action'"
              >
                {{ $t('board.dateDoubled') }}
              </span>
            </Link>
          </template>

          <!-- An empty stage keeps its height, or there is no target there. -->
          <div :class="target === index && 'min-h-11'" />
        </section>
      </div>

      <!-- List — grouped by who owes the next move, not by stage. A board
           shows where something stands, a table shows what is next. -->
      <table v-else class="mt-5 w-full border-collapse">
        <thead>
          <tr>
            <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('board.colInquiry') }}</th>
            <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('board.colDate') }}</th>
            <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('board.colVenue') }}</th>
            <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('board.colStage') }}</th>
            <th class="px-8 pb-2 text-right text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('board.colValue') }}</th>
            <th class="px-8 pb-2 text-right text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('board.colLast') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in groupRows" :key="group.key">
            <tr>
              <td
                class="bg-sunk px-8 pt-4 pb-1.5 align-baseline text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2"
                colspan="6"
              >
                {{ $t(`board.group.${group.key}`) }}
              </td>
            </tr>
            <tr v-for="card in group.rows" :key="card.id" class="[&:hover>td]:bg-sunk">
              <td class="px-8 py-2.5 align-baseline text-[13.5px] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]">
                <Link :href="`/anfragen/${card.leadId}`">{{ card.names }}</Link>
              </td>
              <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2]">
                {{ shortDate(card.date) ?? $t('board.openDate') }}
                <span v-if="card.conflicts.length" class="text-action"> · {{ $t('board.dateDoubled') }}</span>
              </td>
              <td class="px-8 py-2.5 align-baseline text-[13px] text-ink-2 shadow-[inset_0_1px_0_#F0F0F2]">{{ card.venue ?? '—' }}</td>
              <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2]">
                <span class="inline-flex items-baseline gap-[7px] text-[12.5px]">
                  <s
                    class="size-1.5 self-center rounded-full no-underline"
                    :class="STAGE_DOT[columns.findIndex((c) => c.stage === card.stage)] ?? 'bg-foreground'"
                  />
                  {{ columns.find((c) => c.stage === card.stage)?.label }}
                </span>
              </td>
              <td class="px-8 py-2.5 text-right align-baseline text-[13px] tabular-nums shadow-[inset_0_1px_0_#F0F0F2]">
                {{ card.value === null ? '—' : money.format(card.value / 100) }}
              </td>
              <td class="px-8 py-2.5 text-right align-baseline text-[13px] tabular-nums text-ink-2 shadow-[inset_0_1px_0_#F0F0F2]">
                {{ relative(card.lastAt) }}
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <div
        v-if="menu"
        class="fixed z-20 w-[208px] rounded-[12px] bg-paper p-[5px] shadow-[0_8px_24px_rgba(0,0,0,0.16)]"
        :style="{ left: `${menu.x}px`, top: `${menu.y}px` }"
        @click.stop
      >
        <div
          v-for="column in columns"
          :key="column.stage"
          class="rounded-lg px-[9px] py-1.5 text-[12.5px]"
          :class="column.stage === menu.card.stage
            ? 'cursor-default text-mark'
            : 'cursor-pointer hover:bg-sunk'"
          @click="column.stage !== menu.card.stage && move(menu.card.leadId, column.stage)"
        >
          {{ column.label }}
        </div>
      </div>

      <AlertDialog :open="booking !== null" @update:open="(open: boolean) => { if (!open) booking = null }">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{{ $t('board.bookTitle') }}</AlertDialogTitle>
          </AlertDialogHeader>
          <p>{{ $t('board.bookConsequence') }}</p>
          <AlertDialogFooter>
            <AlertDialogAction @click="book">{{ $t('board.book') }}</AlertDialogAction>
            <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  </AdminLayout>
</template>
