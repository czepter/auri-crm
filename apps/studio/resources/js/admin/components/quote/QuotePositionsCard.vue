<script setup lang="ts">
import { computed, ref } from 'vue'
import type { PricingMode, QuoteDraft, QuoteRow } from './quotePayload'
import { quoteRowToLine } from './quotePayload'
import { applyDiscount, span, subtotal } from './quoteTotals'
import { eurosToCents, formatEuros, parseDiscount } from '@/admin/lib/money'
import { moveRow } from '@/admin/lib/rows'
import QuoteInsertRow from '@/admin/components/QuoteInsertRow.vue'
import QuotePositionRow from './QuotePositionRow.vue'
import DiscountInput from './DiscountInput.vue'

const props = defineProps<{
  mode: PricingMode
  quoteId: string
  canManageCatalog: boolean
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
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

function insert(rows: QuoteRow[]) {
  draft.value.rows = [...draft.value.rows, ...rows]
  emit('change')
}

function remove(index: number) {
  draft.value.rows = draft.value.rows.filter((_, i) => i !== index)
  openIndex.value = null
  emit('change')
}

function move(from: number, to: number) {
  const next = moveRow(draft.value.rows, from, to)

  if (next !== draft.value.rows) {
    draft.value.rows = next
    openIndex.value = openIndex.value === from ? to : openIndex.value

    emit('change')
  }
}

const dragging = ref<number | null>(null)
const over = ref<number | null>(null)

function onDragOver(event: DragEvent, index: number) {
  if (dragging.value === null) return

  event.preventDefault()
  over.value = index
}

function onDrop(index: number) {
  if (dragging.value !== null) move(dragging.value, index)

  endDrag()
}

function endDrag() {
  dragging.value = null
  over.value = null
}

const chargedRows = computed(() =>
  props.mode === 'classic' ? draft.value.rows : draft.value.rows.filter((row) => !row.selectable || row.selected),
)

const quoteSubtotal = computed(() => subtotal(chargedRows.value.map(quoteRowToLine), props.mode === 'choice' ? timeTotal.value : 0))

const quoteDiscount = computed(() => parseDiscount(draft.value.discount))

const tiles = computed(() => {
  const lines = draft.value.rows.map(quoteRowToLine)
  const selected = lines.filter((line, index) => !line.selectable || draft.value.rows[index].selected)
  const { minHours, maxHours } = draft.value.slider

  return {
    min: span(lines, minHours ?? 0, rate.value, false),
    preselected: span(selected, hours.value, rate.value, true),
    max: span(lines, maxHours ?? 0, rate.value, true),
  }
})

const total = computed(() =>
  applyDiscount(quoteSubtotal.value, {
    type: quoteDiscount.value.type || null,
    value: quoteDiscount.value.type === 'percentage' && quoteDiscount.value.value !== null ? quoteDiscount.value.value / 100 : quoteDiscount.value.value,
  }),
)
</script>

<template>
  <div class="flex flex-col gap-3">
    <!-- Rechnet mit dem Regler mit statt eine eigene Zeile zu sein: es gibt
         keine Karte, die man dafür öffnen könnte. -->
    <div v-if="mode === 'choice' && rate" class="flex items-center gap-3 rounded-[16px] border border-dashed border-line px-4 py-3">
      <div class="min-w-0 flex-1">
        <div class="truncate text-[13.5px] font-medium">{{ draft.timeTitle || $t('quotes.hoursCount', hours) }}</div>
        <div class="mt-0.5 truncate text-[12px] text-ink-2">
          {{ [draft.timeDescription, $t('quotes.hoursCount', hours), includedImages !== null ? $t('quotes.approxImages', { count: includedImages }) : null].filter(Boolean).join(' · ') }}
        </div>
      </div>
      <span class="flex-none rounded-[10px] bg-sunk-2 px-2 py-0.5 text-[11px] leading-none font-semibold text-ink-2">
        {{ $t('quotes.automatic') }}
      </span>
      <span class="flex-none text-[14px] font-semibold tabular-nums">{{ formatEuros(timeTotal) }}</span>
    </div>

    <div
      v-for="(row, index) in draft.rows"
      :key="row.id ?? index"
      data-position
      class="rounded-[20px] transition-opacity"
      :class="{
        'opacity-40': dragging === index,
        'shadow-[0_-3px_0_var(--ring)]': dragging !== null && over === index && dragging > index,
        'shadow-[0_3px_0_var(--ring)]': dragging !== null && over === index && dragging < index,
      }"
      @dragover="onDragOver($event, index)"
      @drop.prevent="onDrop(index)"
    >
      <QuotePositionRow
        :mode="mode"
        :quote-id="quoteId"
        :can-manage-catalog="canManageCatalog"
        :row="row"
        :open="isOpen(index)"
        @update:open="(value) => setOpen(index, value)"
        @change="emit('change')"
        @remove="remove(index)"
        @move-up="move(index, index - 1)"
        @move-down="move(index, index + 1)"
        @drag-start="dragging = index"
        @drag-end="endDrag"
      />
    </div>

    <div class="flex items-baseline gap-3 pl-1">
      <div class="max-w-[360px] flex-1">
        <QuoteInsertRow :packages="packages" :catalog-items="catalogItems" @insert="insert" />
      </div>
    </div>

    <div class="ml-auto flex w-[220px] flex-col gap-1.5 pt-2">
      <div class="flex justify-between text-[12.5px] text-ink-2">
        <span>{{ $t('quotes.subtotal') }}</span>
        <span class="tabular-nums">{{ formatEuros(quoteSubtotal) }}</span>
      </div>
      <div class="flex items-center justify-between gap-2 text-[12.5px] text-ink-2">
        <span>{{ $t('quotes.discount') }}</span>
        <DiscountInput v-model="draft.discount" class="w-32" @change="emit('change')" />
      </div>
      <div class="flex justify-between border-t border-rule pt-1.5 text-[15px] font-semibold">
        <span>{{ $t('quotes.total') }}</span>
        <span class="tabular-nums">{{ formatEuros(total) }}</span>
      </div>
    </div>

    <div v-if="mode === 'choice'" class="grid grid-cols-3 gap-3 pt-2">
      <div class="rounded-[16px] bg-sunk p-4">
        <div class="text-[11px] leading-none font-medium text-ink-2">{{ $t('quotes.spanMin') }}</div>
        <div class="mt-2 text-[18px] leading-none font-semibold tracking-[-0.02em] tabular-nums">{{ formatEuros(tiles.min) }}</div>
      </div>
      <div class="rounded-[16px] bg-[var(--accent-bg)] p-4 text-[var(--accent)]">
        <div class="text-[11px] leading-none font-medium">{{ $t('quotes.spanPreselected') }}</div>
        <div class="mt-2 text-[18px] leading-none font-semibold tracking-[-0.02em] tabular-nums">{{ formatEuros(tiles.preselected) }}</div>
      </div>
      <div class="rounded-[16px] bg-sunk p-4">
        <div class="text-[11px] leading-none font-medium text-ink-2">{{ $t('quotes.spanMax') }}</div>
        <div class="mt-2 text-[18px] leading-none font-semibold tracking-[-0.02em] tabular-nums">{{ formatEuros(tiles.max) }}</div>
      </div>
    </div>
  </div>
</template>
