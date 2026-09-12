<script setup lang="ts">
import { computed, ref } from 'vue'
import type { QuoteDraft, QuoteRow, SliderRow } from './quotePayload'
import { applyDiscount, subtotal } from './quoteTotals'
import { eurosToCents, formatEuros, parseDiscount } from '@/admin/lib/money'
import QuoteInsertRow from '@/admin/components/QuoteInsertRow.vue'
import QuotePositionRow from './QuotePositionRow.vue'
import { Input } from '@/ui/input'

const props = defineProps<{
  mode: 'classic' | 'choice'
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
  if (props.mode === 'classic') {
    draft.value.rows = [...draft.value.rows, ...rows]
  } else {
    draft.value.slider.rows = [
      ...draft.value.slider.rows,
      ...rows.map(
        (row): SliderRow => ({
          title: row.title,
          outcome: row.description,
          unitPrice: row.unitPrice,
          selectable: true,
          selected: false,
          hasImage: false,
        }),
      ),
    ]
  }

  emit('change')
}

function remove(index: number) {
  if (props.mode === 'classic') {
    draft.value.rows = draft.value.rows.filter((_, i) => i !== index)
  } else {
    draft.value.slider.rows = draft.value.slider.rows.filter((_, i) => i !== index)
  }

  openIndex.value = null
  emit('change')
}

function lineInput(row: QuoteRow) {
  const discount = parseDiscount(row.discount)

  return {
    quantity: row.quantity,
    unitPrice: eurosToCents(row.unitPrice) ?? 0,
    discountType: discount.type || null,
    discountValue: discount.type === 'percentage' && discount.value !== null ? discount.value / 100 : discount.value,
  }
}

const chargedRows = computed(() =>
  props.mode === 'classic'
    ? draft.value.rows.map(lineInput)
    : draft.value.slider.rows
        .filter((row) => !row.selectable || row.selected)
        .map((row) => ({ quantity: 1, unitPrice: eurosToCents(row.unitPrice) ?? 0, discountType: null, discountValue: null })),
)

const quoteSubtotal = computed(() => subtotal(chargedRows.value, props.mode === 'choice' ? timeTotal.value : 0))

const quoteDiscount = computed(() => parseDiscount(draft.value.discount))

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
    <div v-if="mode === 'choice'" class="flex items-center gap-3 rounded-[16px] border border-dashed border-line px-4 py-3">
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

    <QuotePositionRow
      v-for="(row, index) in mode === 'classic' ? draft.rows : draft.slider.rows"
      :key="row.id ?? index"
      :mode="mode"
      :quote-id="quoteId"
      :can-manage-catalog="canManageCatalog"
      :row="row"
      :open="isOpen(index)"
      @update:open="(value) => setOpen(index, value)"
      @change="emit('change')"
      @remove="remove(index)"
    />

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
        <Input v-model="draft.discount" class="h-7 w-24 text-right tabular-nums" type="text" inputmode="decimal" @blur="emit('change')" />
      </div>
      <div class="flex justify-between border-t border-rule pt-1.5 text-[15px] font-semibold">
        <span>{{ $t('quotes.total') }}</span>
        <span class="tabular-nums">{{ formatEuros(total) }}</span>
      </div>
    </div>
  </div>
</template>
