<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

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

function move(leadId: string, stage: string) {
  router.post(`/anfragen/${leadId}/stufe`, { stage }, { preserveScroll: true })
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
</script>

<template>
  <AdminLayout active="anfragen">
    <div data-theme="crm" @click="menu = null">
      <div class="hrow">
        <h1>{{ $t('board.title') }}</h1>

        <span class="seg" style="margin-left: auto">
          <button type="button" :class="{ on: view === 'board' }" @click="choose('board')">
            {{ $t('board.viewBoard') }}
          </button>
          <button type="button" :class="{ on: view === 'liste' }" @click="choose('liste')">
            {{ $t('board.viewList') }}
          </button>
        </span>

        <Link class="btn" href="/anfragen/neu">{{ $t('board.create') }}</Link>
      </div>

      <!-- Board — list columns, no cards. Grey cards on a white ground invert
           the material order, in which the container carries material and the
           content stays white. -->
      <div v-if="view === 'board'" class="cols">
        <section
          v-for="(column, index) in columns"
          :key="column.stage"
          class="col"
          :class="{ target: target === index, done: DONE_STAGES.includes(column.stage) }"
          @dragover="onDragOver($event, index, null)"
          @drop.prevent="onDrop(index)"
        >
          <header>
            <b>{{ column.label }}</b>
            <span class="s">{{ column.cards.length }}<template v-if="column.total"> · {{ money.format(column.total / 100) }}</template></span>
          </header>

          <template v-for="(card, row) in column.cards" :key="card.id">
            <div v-if="target === index && insertAt === row" class="dropline" />

            <Link
              class="card"
              :class="{ moving: moving?.id === card.id }"
              :href="`/auftraege/${card.id}`"
              draggable="true"
              @dragstart="onDragStart($event, card, index)"
              @dragend="reset"
              @dragover="onDragOver($event, index, row)"
              @keydown="onKey($event, card, index)"
              @contextmenu="openMenu($event, card)"
            >
              <span class="nm">{{ card.names }}</span>
              <span class="mt">
                {{ shortDate(card.date) ?? $t('board.openDate') }}
                <template v-if="card.venue"> · {{ card.venue }}</template>
              </span>
              <!-- On both affected rows and without naming anyone: who
                   collides is the other marked row in the same picture. -->
              <span v-if="card.conflicts.length" class="w">{{ $t('board.dateDoubled') }}</span>
            </Link>
          </template>

          <!-- An empty stage keeps its height, or there is no target there. -->
          <div class="stub" />
        </section>
      </div>

      <!-- List — grouped by who owes the next move, not by stage. A board
           shows where something stands, a table shows what is next. -->
      <table v-else style="margin-top: var(--s5)">
        <thead>
          <tr>
            <th>{{ $t('board.colInquiry') }}</th>
            <th>{{ $t('board.colDate') }}</th>
            <th>{{ $t('board.colVenue') }}</th>
            <th>{{ $t('board.colStage') }}</th>
            <th class="r">{{ $t('board.colValue') }}</th>
            <th class="r">{{ $t('board.colLast') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in groupRows" :key="group.key">
            <tr><td class="grp" colspan="6">{{ $t(`board.group.${group.key}`) }}</td></tr>
            <tr v-for="card in group.rows" :key="card.id">
              <td class="nm"><Link :href="`/auftraege/${card.id}`">{{ card.names }}</Link></td>
              <td>
                {{ shortDate(card.date) ?? $t('board.openDate') }}
                <span v-if="card.conflicts.length" class="w"> · {{ $t('board.dateDoubled') }}</span>
              </td>
              <td class="mu">{{ card.venue ?? '—' }}</td>
              <td>
                <span class="stufe">
                  <s :class="`s${columns.findIndex((c) => c.stage === card.stage) + 1}`" />
                  {{ columns.find((c) => c.stage === card.stage)?.label }}
                </span>
              </td>
              <td class="r">{{ card.value === null ? '—' : money.format(card.value / 100) }}</td>
              <td class="r mu">{{ relative(card.lastAt) }}</td>
            </tr>
          </template>
        </tbody>
      </table>

      <div
        v-if="menu"
        class="stagemenu"
        :style="{ left: `${menu.x}px`, top: `${menu.y}px` }"
        @click.stop
      >
        <div
          v-for="column in columns"
          :key="column.stage"
          :class="{ off: column.stage === menu.card.stage }"
          @click="column.stage !== menu.card.stage && move(menu.card.leadId, column.stage)"
        >
          {{ column.label }}
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
