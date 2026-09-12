<script setup lang="ts">
import { computed } from 'vue'
import type { QuoteRow, SliderRow } from './quotePayload'
import { lineAmount } from './quoteTotals'
import { eurosToCents, formatEuros, parseDiscount } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { ChoiceGroup } from '@/ui/choice-group'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import QuoteCard from './QuoteCard.vue'

const props = defineProps<{
  mode: 'classic' | 'choice'
  quoteId: string
  canManageCatalog: boolean
  row: QuoteRow | SliderRow
}>()

const open = defineModel<boolean>('open', { required: true })

const emit = defineEmits<{ change: []; remove: [] }>()

const classicRow = computed(() => props.row as QuoteRow)
const sliderRow = computed(() => props.row as SliderRow)

const description = computed({
  get: () => (props.mode === 'classic' ? classicRow.value.description : sliderRow.value.outcome),
  set: (value: string) => {
    if (props.mode === 'classic') {
      classicRow.value.description = value
    } else {
      sliderRow.value.outcome = value
    }
  },
})

const portalStatus = computed(() => (sliderRow.value.selectable ? (sliderRow.value.selected ? 'preselected' : 'pickable') : 'included'))
const portalLabels: Record<string, string> = {
  included: 'quotes.portalIncluded',
  preselected: 'quotes.preselected',
  pickable: 'quotes.pickable',
}

function setPortalStatus(value: string) {
  sliderRow.value.selectable = value !== 'included'
  sliderRow.value.selected = value === 'preselected'
  emit('change')
}

const amount = computed(() => {
  if (props.mode === 'choice') {
    return lineAmount({ quantity: 1, unitPrice: eurosToCents(sliderRow.value.unitPrice) ?? 0, discountType: null, discountValue: null })
  }

  const discount = parseDiscount(classicRow.value.discount)

  return lineAmount({
    quantity: classicRow.value.quantity,
    unitPrice: eurosToCents(classicRow.value.unitPrice) ?? 0,
    discountType: discount.type || null,
    discountValue: discount.type === 'percentage' && discount.value !== null ? discount.value / 100 : discount.value,
  })
})
</script>

<template>
  <QuoteCard v-model:open="open" :title="row.title || $t('quotes.itemTitle')">
    <template #summary>
      <span
        v-if="mode === 'choice'"
        class="rounded-[10px] px-2 py-0.5 text-[11px] leading-none font-semibold"
        :class="portalStatus === 'included' ? 'bg-sunk-2 text-ink-2' : 'bg-[var(--accent-bg)] text-[var(--accent)]'"
      >{{ $t(portalLabels[portalStatus]) }}</span>
      <span class="tabular-nums">{{ formatEuros(amount) }}</span>
    </template>

    <div class="flex flex-col gap-4">
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('quotes.itemTitle') }}</Label>
        <Input v-model="row.title" type="text" @blur="emit('change')" />
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('quotes.itemDescription') }}</Label>
        <Input v-model="description" type="text" @blur="emit('change')" />
      </div>

      <div class="grid gap-4" :class="mode === 'classic' ? 'grid-cols-3' : 'grid-cols-1'">
        <div v-if="mode === 'classic'" class="flex min-w-0 flex-col gap-1.5">
          <Label>{{ $t('quotes.itemQuantity') }}</Label>
          <Input v-model.number="classicRow.quantity" class="tabular-nums" type="number" min="1" @blur="emit('change')" />
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label>{{ $t('quotes.itemPrice') }}</Label>
          <InputGroup>
            <InputGroupInput
              :model-value="mode === 'classic' ? classicRow.unitPrice : sliderRow.unitPrice"
              class="tabular-nums"
              type="text"
              inputmode="decimal"
              :placeholder="$t('quotes.itemPriceHint')"
              @update:model-value="(value) => (mode === 'classic' ? (classicRow.unitPrice = String(value)) : (sliderRow.unitPrice = String(value)))"
              @blur="emit('change')"
            />
            <InputGroupAddon align="inline-end">€</InputGroupAddon>
          </InputGroup>
        </div>
        <div v-if="mode === 'classic'" class="flex min-w-0 flex-col gap-1.5">
          <Label>{{ $t('quotes.discount') }}</Label>
          <Input v-model="classicRow.discount" class="tabular-nums" type="text" inputmode="decimal" @blur="emit('change')" />
        </div>
      </div>

      <ChoiceGroup
        v-if="mode === 'choice'"
        :label="$t('quotes.inPortal')"
        :model-value="portalStatus"
        :options="Object.entries(portalLabels).map(([value, label]) => ({ value, label: $t(label) }))"
        variant="segmented"
        class="self-start"
        @update:model-value="setPortalStatus"
      />

      <label v-if="mode === 'classic' && canManageCatalog" class="flex items-center gap-2">
        <Checkbox
          :model-value="classicRow.remember"
          @update:model-value="(value) => { classicRow.remember = value === true; emit('change') }"
        />
        <span class="text-[12.5px] text-ink-2">{{ $t('quotes.remember') }}</span>
      </label>

      <div class="flex items-center justify-between pt-1">
        <Button variant="ghost" tone="accent" type="button" @click="emit('remove')">{{ $t('common.remove') }}</Button>
        <span class="text-[15px] font-semibold tabular-nums">{{ formatEuros(amount) }}</span>
      </div>
    </div>
  </QuoteCard>
</template>
