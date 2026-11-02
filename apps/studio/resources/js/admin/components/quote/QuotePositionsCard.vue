<script setup lang="ts">
import { computed, ref } from 'vue'
import type { PricingMode, QuoteDraft, QuoteRow, VatSettings } from './quotePayload'
import { quoteRowToLine } from './quotePayload'
import { applyDiscount, lineAmount, span, subtotal, vatLines } from '@/lib/quoteTotals'
import { basisPointsToPercent, eurosToCents, formatEuros, parseDiscount } from '@/lib/money'
import { moveRow } from '@/admin/lib/rows'
import { Label } from '@/ui/label'
import { Switch } from '@/ui/switch'
import QuoteInsertRow from '@/admin/components/quote/QuoteInsertRow.vue'
import QuotePositionRow from './QuotePositionRow.vue'
import DiscountInput from './DiscountInput.vue'
import DragHandle from './DragHandle.vue'

const props = defineProps<{
  mode: PricingMode
  quoteId: string
  canManageCatalog: boolean
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
  vatSettings: VatSettings
}>()

const draft = defineModel<QuoteDraft>({ required: true })
const emit = defineEmits<{ change: [] }>()

// At most one position card is open at a time — the index it carries.
const openIndex = ref<number | null>(null)

function isOpen(index: number) {
  return openIndex.value === index
}

function setOpen(index: number, value: boolean) {
  openIndex.value = value ? index : null
}

const rate = computed(() => eurosToCents(draft.value.slider.hourlyRate) ?? 0)
const hours = computed(() => draft.value.slider.hours ?? draft.value.slider.minHours ?? 0)
const timeTotal = computed(() => hours.value * rate.value)

const includedImages = computed(() =>
  draft.value.slider.imagesPerHour ? hours.value * draft.value.slider.imagesPerHour : null,
)

const showTime = computed(() => props.mode === 'choice' && rate.value > 0)

type Entry = { kind: 'time' } | { kind: 'row'; row: QuoteRow; index: number }

// Die Zeitzeile ist keine Position, steht aber zwischen ihnen: timeSortOrder
// ist ihr Platz in dieser Liste, 0 = vor der ersten Position.
const entries = computed<Entry[]>(() => {
  const list: Entry[] = draft.value.rows.map((row, index) => ({ kind: 'row', row, index }))

  if (showTime.value) {
    list.splice(Math.min(draft.value.timeSortOrder, list.length), 0, { kind: 'time' })
  }

  return list
})

function insert(rows: QuoteRow[]) {
  draft.value.rows = [...draft.value.rows, ...rows]
  emit('change')
}

function remove(index: number) {
  if (index < draft.value.timeSortOrder) {
    draft.value.timeSortOrder -= 1
  }

  draft.value.rows = draft.value.rows.filter((_, i) => i !== index)
  openIndex.value = null
  emit('change')
}

function move(from: number, to: number) {
  const next = moveRow(entries.value, from, to)

  if (next === entries.value) {
    return
  }

  const openRow = openIndex.value === null ? null : draft.value.rows[openIndex.value]
  const time = next.findIndex((entry) => entry.kind === 'time')

  draft.value.rows = next.flatMap((entry) => (entry.kind === 'row' ? [entry.row] : []))
  if (time !== -1) draft.value.timeSortOrder = time
  openIndex.value = openRow ? draft.value.rows.indexOf(openRow) : null

  emit('change')
}

const dragging = ref<number | null>(null)
const over = ref<number | null>(null)

// Die Liste zeigt schon beim Ziehen die Reihenfolge nach dem Loslassen; die
// gezogene Karte steht als Platzhalter an ihrem neuen Platz.
const displayed = computed(() =>
  dragging.value === null || over.value === null ? entries.value : moveRow(entries.value, dragging.value, over.value),
)

function at(entry: Entry) {
  return entries.value.indexOf(entry)
}

// Erst nach dem dragstart: Chrome baut das Ziehbild aus der Quelle und bricht
// ab, wenn sie im selben Tick unsichtbar wird oder zuklappt.
let pendingDrag: ReturnType<typeof setTimeout> | undefined

function startDrag(position: number) {
  pendingDrag = setTimeout(() => {
    openIndex.value = null
    dragging.value = position
    over.value = position
  })
}

function onDragOver(event: DragEvent, entry?: Entry) {
  if (dragging.value === null) return

  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  if (!entry || over.value === null) return

  // Gezählt wird in der angezeigten Liste; die ohne Platzhalter hat dieselbe
  // Reihenfolge, also landet die Karte genau dort.
  const target = displayed.value.indexOf(entry)
  const box = (event.currentTarget as HTMLElement).getBoundingClientRect()
  const upperHalf = event.clientY < box.top + box.height / 2

  // Erst jenseits der Mitte der Nachbarkarte: sonst schiebt jeder Tausch sie
  // wieder unter den Zeiger und die Liste springt hin und her.
  if ((target > over.value && upperHalf) || (target < over.value && !upperHalf)) return

  over.value = target
}

function onDrop() {
  if (dragging.value !== null && over.value !== null) move(dragging.value, over.value)

  endDrag()
}

function endDrag() {
  clearTimeout(pendingDrag)
  dragging.value = null
  over.value = null
}

const chargedRows = computed(() => draft.value.rows.filter((row) => !row.selectable || row.selected))

const quoteSubtotal = computed(() => subtotal(chargedRows.value.map(quoteRowToLine), props.mode === 'choice' ? timeTotal.value : 0))

const quoteDiscount = computed(() => parseDiscount(draft.value.discount))

const tiles = computed(() => {
  const lines = draft.value.rows.map(quoteRowToLine)
  const selected = lines.filter((line, index) => !line.selectable || draft.value.rows[index].selected)
  const { minHours, maxHours } = draft.value.slider

  return {
    min: applyDiscount(span(lines, minHours ?? 0, rate.value, false), discountArg.value),
    preselected: applyDiscount(span(selected, hours.value, rate.value, true), discountArg.value),
    max: applyDiscount(span(lines, maxHours ?? 0, rate.value, true), discountArg.value),
  }
})

const discountArg = computed(() => ({
  type: quoteDiscount.value.type || null,
  value: quoteDiscount.value.type === 'percentage' && quoteDiscount.value.value !== null ? quoteDiscount.value.value / 100 : quoteDiscount.value.value,
}))

const total = computed(() => applyDiscount(quoteSubtotal.value, discountArg.value))

// Q-04 friert das erst beim Versenden ein (Quote::computeVat()) — bis dahin
// zeigt der Builder dieselbe Live-Rechnung aus den aktuellen
// Studio-Einstellungen, die die Portal-Vorschau daneben auch zeigt.
// #412: eine Zeile je Steuersatz. Bei einem Satz ist das die eine Zeile,
// die hier immer stand.
const vatRows = computed(() =>
  vatLines(
    chargedRows.value.map((row) => ({ netTotal: lineAmount(quoteRowToLine(row)), vatRate: row.vatRate })),
    props.vatSettings.rate,
    total.value,
  ),
)
const vat = computed(() => vatRows.value.reduce((sum, line) => sum + line.vat, 0))
</script>

<template>
  <div class="flex flex-col gap-3">
    <!-- Die ganze Liste nimmt den Drop an, auch die Lücken zwischen den Zeilen. -->
    <div class="flex flex-col divide-y divide-rule" @dragover="onDragOver($event)" @drop.prevent="onDrop">
    <div
      v-for="entry in displayed"
      :key="entry.kind === 'time' ? 'time' : (entry.row.id ?? `row-${entry.index}`)"
      data-position
      :class="
        dragging === null
          ? ''
          : entry === entries[dragging]
            ? 'rounded-[12px] bg-[var(--accent-bg)] outline-2 outline-dashed outline-ring [&>*]:invisible'
            : 'rounded-[12px] outline-2 outline-offset-2 outline-dashed outline-ring/35'
      "
      @dragover.stop="onDragOver($event, entry)"
    >
      <!-- Rechnet mit dem Regler mit: kein Inhalt zum Aufklappen, nur ein Platz. -->
      <div v-if="entry.kind === 'time'" class="flex items-center">
        <DragHandle
          @drag-start="startDrag(at(entry))"
          @drag-end="endDrag"
          @move-up="move(at(entry), at(entry) - 1)"
          @move-down="move(at(entry), at(entry) + 1)"
        />
        <div class="flex min-w-0 flex-1 items-center gap-3 py-4 pr-1 pl-5">
          <div class="min-w-0 flex-1">
            <div class="truncate text-ui font-semibold tracking-[-0.02em]">{{ draft.timeTitle || $t('quotes.hoursCount', hours) }}</div>
            <div class="mt-0.5 truncate text-small text-ink-2">
              {{ [draft.timeDescription, $t('quotes.hoursCount', hours), includedImages !== null ? $t('quotes.approxImages', { count: includedImages }) : null].filter(Boolean).join(' · ') }}
            </div>
          </div>
          <span class="flex items-center gap-2 text-small text-ink-2">
            <span class="rounded-[10px] bg-sunk-2 px-2 py-0.5 text-small leading-none font-semibold">{{ $t('quotes.automatic') }}</span>
            <span class="tabular-nums">{{ formatEuros(timeTotal) }}</span>
          </span>
        </div>
      </div>

      <QuotePositionRow
        v-else
        :mode="mode"
        :quote-id="quoteId"
        :can-manage-catalog="canManageCatalog"
        :row="entry.row"
        :vat-mode="vatSettings.mode"
        :open="isOpen(entry.index)"
        @update:open="(value) => setOpen(entry.index, value)"
        @change="emit('change')"
        @remove="remove(entry.index)"
        @move-up="move(at(entry), at(entry) - 1)"
        @move-down="move(at(entry), at(entry) + 1)"
        @drag-start="startDrag(at(entry))"
        @drag-end="endDrag"
      />
    </div>
    </div>

    <div class="flex items-baseline gap-3 pl-1">
      <div class="flex-1">
        <QuoteInsertRow :packages="packages" :catalog-items="catalogItems" @insert="insert" />
      </div>
    </div>

    <div class="ml-auto flex w-[220px] flex-col gap-1.5 pt-2">
      <div class="flex justify-between text-small text-ink-2">
        <span>{{ $t('quotes.subtotal') }}</span>
        <span class="tabular-nums">{{ formatEuros(quoteSubtotal) }}</span>
      </div>
      <div class="flex items-center justify-between gap-2 text-small text-ink-2">
        <span>{{ $t('quotes.discount') }}</span>
        <DiscountInput v-model="draft.discount" class="w-32" @change="emit('change')" />
      </div>
      <div v-if="draft.discount || draft.rows.some((row) => row.discount)" class="flex items-center justify-between gap-2">
        <Label for="discount-hidden" variant="inline" class="text-small text-ink-2">{{ $t('quotes.discountHidden') }}</Label>
        <Switch
          id="discount-hidden"
          :model-value="draft.discountHidden"
          @update:model-value="((draft.discountHidden = $event), emit('change'))"
        />
      </div>
      <div class="flex justify-between border-t border-rule pt-1.5 text-body font-semibold">
        <span>{{ $t('quotes.total') }}</span>
        <span class="tabular-nums">{{ formatEuros(total) }}</span>
      </div>

      <template v-if="vatSettings.mode === 'standard'">
        <div v-for="line in vatRows" :key="line.rate" class="flex justify-between text-small text-ink-2">
          <span>{{ $t('quotes.vat', { rate: basisPointsToPercent(line.rate) }) }}</span>
          <span class="tabular-nums">{{ formatEuros(line.vat) }}</span>
        </div>
        <div class="flex justify-between border-t border-rule pt-1.5 text-body font-semibold">
          <span>{{ $t('quotes.total') }}</span>
          <span class="tabular-nums">{{ formatEuros(total + vat) }}</span>
        </div>
      </template>
      <p v-else-if="vatSettings.note" class="text-small leading-[1.45] text-ink-2">{{ vatSettings.note }}</p>
    </div>

    <div v-if="mode === 'choice'" class="grid grid-cols-3 gap-3 pt-2">
      <div class="rounded-[16px] bg-sunk p-4">
        <div class="text-small leading-none font-medium text-ink-2">{{ $t('quotes.spanMin') }}</div>
        <div class="mt-2 text-title leading-none font-semibold tracking-[-0.02em] tabular-nums">{{ formatEuros(tiles.min) }}</div>
      </div>
      <div class="rounded-[16px] bg-[var(--accent-bg)] p-4 text-[var(--accent)]">
        <div class="text-small leading-none font-medium">{{ $t('quotes.spanPreselected') }}</div>
        <div class="mt-2 text-title leading-none font-semibold tracking-[-0.02em] tabular-nums">{{ formatEuros(tiles.preselected) }}</div>
      </div>
      <div class="rounded-[16px] bg-sunk p-4">
        <div class="text-small leading-none font-medium text-ink-2">{{ $t('quotes.spanMax') }}</div>
        <div class="mt-2 text-title leading-none font-semibold tracking-[-0.02em] tabular-nums">{{ formatEuros(tiles.max) }}</div>
      </div>
    </div>
  </div>
</template>
