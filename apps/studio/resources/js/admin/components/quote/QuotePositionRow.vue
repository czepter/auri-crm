<script setup lang="ts">
import { computed } from 'vue'
import type { PricingMode, QuoteRow } from './quotePayload'
import { quoteRowToLine } from './quotePayload'
import { lineAmount } from './quoteTotals'
import { formatEuros } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { ChoiceGroup } from '@/ui/choice-group'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import QuoteCard from './QuoteCard.vue'

const props = defineProps<{
  mode: PricingMode
  quoteId: string
  canManageCatalog: boolean
  row: QuoteRow
  isFirst: boolean
  isLast: boolean
}>()

const open = defineModel<boolean>('open', { required: true })

const emit = defineEmits<{ change: []; remove: []; moveUp: []; moveDown: [] }>()

const portalStatus = computed(() => (props.row.selectable ? (props.row.selected ? 'preselected' : 'pickable') : 'included'))
const portalLabels: Record<string, string> = {
  included: 'quotes.portalIncluded',
  preselected: 'quotes.preselected',
  pickable: 'quotes.pickable',
}

function setPortalStatus(value: string) {
  props.row.selectable = value !== 'included'
  props.row.selected = value === 'preselected'
  emit('change')
}

const amount = computed(() => lineAmount(quoteRowToLine(props.row)))
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
        <Input v-model="row.description" type="text" @blur="emit('change')" />
      </div>

      <div v-if="mode === 'choice'" class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('quotes.itemOutcome') }}</Label>
        <Input v-model="row.outcome" type="text" @blur="emit('change')" />
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label>{{ $t('quotes.itemQuantity') }}</Label>
          <Input v-model.number="row.quantity" class="tabular-nums" type="number" min="1" @blur="emit('change')" />
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label>{{ $t('quotes.itemPrice') }}</Label>
          <InputGroup>
            <InputGroupInput
              v-model="row.unitPrice"
              class="tabular-nums"
              type="text"
              inputmode="decimal"
              :placeholder="$t('quotes.itemPriceHint')"
              @blur="emit('change')"
            />
            <InputGroupAddon align="inline-end">€</InputGroupAddon>
          </InputGroup>
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label>{{ $t('quotes.discount') }}</Label>
          <Input v-model="row.discount" class="tabular-nums" type="text" inputmode="decimal" @blur="emit('change')" />
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

      <label v-if="canManageCatalog" class="flex items-center gap-2">
        <Checkbox
          :model-value="row.remember"
          @update:model-value="(value) => { row.remember = value === true; emit('change') }"
        />
        <span class="text-[12.5px] text-ink-2">{{ $t('quotes.remember') }}</span>
      </label>

      <div class="flex items-center justify-between pt-1">
        <div class="flex items-center gap-1">
          <Button variant="ghost" tone="accent" type="button" @click="emit('remove')">{{ $t('common.remove') }}</Button>
          <Button
            variant="ghost"
            type="button"
            :disabled="isFirst"
            :aria-label="$t('quotes.moveUp')"
            @click="emit('moveUp')"
          >
            <svg viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
          </Button>
          <Button
            variant="ghost"
            type="button"
            :disabled="isLast"
            :aria-label="$t('quotes.moveDown')"
            @click="emit('moveDown')"
          >
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12l7 7 7-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
          </Button>
        </div>
        <span class="text-[15px] font-semibold tabular-nums">{{ formatEuros(amount) }}</span>
      </div>
    </div>
  </QuoteCard>
</template>
