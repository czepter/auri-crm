<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { eurosToCents, formatEuros, parseDiscount } from '@/admin/lib/money'
import { moveRow } from '@/admin/lib/rows'
import QuoteInsertRow from '@/admin/components/QuoteInsertRow.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'

export type QuoteRow = {
  title: string
  description: string
  quantity: number
  unitPrice: string
  discount: string
  remember: boolean
}

const props = defineProps<{
  canManageCatalog: boolean
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
}>()

const rows = defineModel<QuoteRow[]>({ required: true })

const emit = defineEmits<{ change: [] }>()

const titleInputs = ref<HTMLInputElement[]>([])
const dragFrom = ref<number | null>(null)

/**
 * Dieselbe Rechnung, die Quote::recalculateTotal() serverseitig führt — hier
 * nur, damit die Summe nicht erst nach dem Speichern erscheint. Verbindlich
 * bleibt die Zahl, die der Server zurückgibt.
 */
function netTotal(row: QuoteRow): number {
  const unit = eurosToCents(row.unitPrice)

  if (unit === null || Number.isNaN(unit)) {
    return 0
  }

  const line = unit * row.quantity
  const { type, value } = parseDiscount(row.discount)

  if (type === 'percentage' && value !== null) {
    return line - Math.round((line * value) / 10000)
  }

  return type === 'amount' && value !== null ? Math.max(0, line - value) : line
}

const subtotal = computed(() => rows.value.reduce((sum, row) => sum + netTotal(row), 0))

function insert(added: QuoteRow[]) {
  if (!added.length) {
    return
  }

  const first = rows.value.length
  rows.value = [...rows.value, ...added]
  emit('change')
  // In die erste eingefügte Zeile, nicht in die letzte: bei einem Paket
  // beginnt das Nacharbeiten oben.
  nextTick(() => titleInputs.value[first]?.focus())
}

function removeRow(index: number) {
  rows.value = rows.value.filter((_, i) => i !== index)
  emit('change')
}

function move(from: number, to: number) {
  const next = moveRow(rows.value, from, to)

  if (next !== rows.value) {
    rows.value = next
    emit('change')
  }
}

function drop(to: number) {
  if (dragFrom.value !== null) {
    move(dragFrom.value, to)
    dragFrom.value = null
  }
}

defineExpose({ subtotal })
</script>

<template>
  <div>
    <div
      class="grid grid-cols-[24px_1fr_64px_112px_96px_104px_32px_32px] items-center gap-x-3 border-b border-border pb-1.5 text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint"
    >
      <span />
      <span>{{ $t('quotes.itemTitle') }}</span>
      <span class="text-right">{{ $t('quotes.itemQuantity') }}</span>
      <span class="text-right">{{ $t('quotes.itemPrice') }}</span>
      <span class="text-right">{{ $t('quotes.discount') }}</span>
      <span class="text-right">{{ $t('quotes.total') }}</span>
      <span />
      <span />
    </div>

    <div
      v-for="(row, index) in rows"
      :key="index"
      class="grid grid-cols-[24px_1fr_64px_112px_96px_104px_32px_32px] items-start gap-x-3 border-b border-border/60 py-2"
    >
      <!-- Ein Knopf, nicht nur ein Ziehziel: mit der Maus ziehen, mit der
           Tastatur die Pfeiltasten — sonst wäre die Reihenfolge nicht bedienbar. -->
      <button
        type="button"
        class="cursor-grab pt-2 text-left text-ink-2"
        :aria-label="$t('quotes.move')"
        draggable="true"
        @dragstart="dragFrom = index"
        @dragover.prevent
        @drop="drop(index)"
        @keydown.up.prevent="move(index, index - 1)"
        @keydown.down.prevent="move(index, index + 1)"
      >⠿</button>

      <div class="flex min-w-0 flex-col gap-1">
        <Input
          :ref="(element: any) => (titleInputs[index] = element?.$el ?? element)"
          v-model="row.title"
          type="text"
          :aria-label="$t('quotes.itemTitle')"
          required
          @blur="emit('change')"
        />
        <Input
          v-model="row.description"
          class="text-[12px]"
          type="text"
          :aria-label="$t('quotes.itemDescription')"
          :placeholder="$t('quotes.itemDescription')"
          @blur="emit('change')"
        />
      </div>

      <Input
        v-model.number="row.quantity"
        class="tabular-nums"
        type="number"
        min="1"
        :aria-label="$t('quotes.itemQuantity')"
        required
        @blur="emit('change')"
      />

      <InputGroup>
        <InputGroupInput
          v-model="row.unitPrice"
          class="tabular-nums"
          type="text"
          inputmode="decimal"
          :aria-label="$t('quotes.itemPrice')"
          :placeholder="$t('quotes.itemPriceHint')"
          @blur="emit('change')"
        />
        <InputGroupAddon align="inline-end">€</InputGroupAddon>
      </InputGroup>

      <Input
        v-model="row.discount"
        class="tabular-nums"
        type="text"
        inputmode="decimal"
        :aria-label="$t('quotes.discount')"
        @blur="emit('change')"
      />

      <span class="pt-2 text-right text-[13px] tabular-nums">{{ formatEuros(netTotal(row)) }}</span>

      <!-- Angehakt heisst: es gibt einen CatalogItem mit diesem Titel. Der
           Zustand steht in der Bibliothek, nicht auf der Angebotszeile. -->
      <Checkbox
        v-if="canManageCatalog"
        class="mt-2.5"
        :model-value="row.remember"
        :aria-label="$t('quotes.remember')"
        @update:model-value="(value) => { row.remember = value === true; emit('change') }"
      />
      <span v-else />

      <Button variant="ghost" tone="accent" type="button" :aria-label="$t('common.remove')" @click="removeRow(index)">
        ×
      </Button>
    </div>

    <div class="mt-3 flex items-baseline gap-3">
      <div class="max-w-[360px] flex-1">
        <QuoteInsertRow :packages="props.packages" :catalog-items="props.catalogItems" @insert="insert" />
      </div>
      <span class="ml-auto text-[17px] font-semibold tabular-nums">{{ formatEuros(subtotal) }}</span>
    </div>
  </div>
</template>
