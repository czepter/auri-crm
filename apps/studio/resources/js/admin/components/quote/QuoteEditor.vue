<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { QuoteDraft } from './quotePayload'
import { subtotal } from './quoteTotals'
import { eurosToCents, formatEuros, parseDiscount } from '@/admin/lib/money'
import { monthDate } from '@/admin/lib/dates'
import { ChoiceGroup } from '@/ui/choice-group'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'
import QuoteCard from './QuoteCard.vue'
import QuoteRecipientCard from './QuoteRecipientCard.vue'
import QuoteTimeCard from './QuoteTimeCard.vue'
import QuotePositionsCard from './QuotePositionsCard.vue'
import QuoteTermsCard from './QuoteTermsCard.vue'

const props = defineProps<{
  quoteId: string
  quote: App.Quotes.Data.QuoteData
  canManageCatalog: boolean
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
}>()

const draft = defineModel<QuoteDraft>({ required: true })
const emit = defineEmits<{ change: [] }>()
const { t } = useI18n()

// Kein eigenes Feld auf dem Entwurf: klassisch oder Auswahl bleibt weiter
// daran erkennbar, ob ein Stundensatz steht (Q-14). Der Umschalter macht das
// nur zu einer bewussten Entscheidung statt einem Nebeneffekt des Tippens.
const mode = ref<'classic' | 'choice'>(eurosToCents(draft.value.slider.hourlyRate) ? 'choice' : 'classic')

function setMode(value: string) {
  mode.value = value as 'classic' | 'choice'

  if (mode.value === 'classic') {
    draft.value.slider.hourlyRate = ''
    emit('change')
  }
}

const open = reactive({
  recipient: true,
  time: true,
  positions: true,
  terms: false,
  text: false,
})

const rate = computed(() => eurosToCents(draft.value.slider.hourlyRate) ?? 0)

const recipientSummary = computed(() =>
  [
    props.quote.recipientName,
    draft.value.recipientStreet,
    [draft.value.recipientPostcode, draft.value.recipientCity].filter(Boolean).join(' '),
  ]
    .filter(Boolean)
    .join(' · '),
)

const timeSummary = computed(() =>
  [
    draft.value.timeTitle,
    rate.value ? t('quotes.perHour', { amount: formatEuros(rate.value) }) : null,
    draft.value.slider.minHours !== null && draft.value.slider.maxHours !== null
      ? t('quotes.hoursRange', { min: draft.value.slider.minHours, max: draft.value.slider.maxHours })
      : null,
  ]
    .filter(Boolean)
    .join(' · '),
)

function lineInput(row: { quantity: number; unitPrice: string; discount: string }) {
  const discount = parseDiscount(row.discount)

  return {
    quantity: row.quantity,
    unitPrice: eurosToCents(row.unitPrice) ?? 0,
    discountType: discount.type || null,
    discountValue: discount.type === 'percentage' && discount.value !== null ? discount.value / 100 : discount.value,
  }
}

const positionsTotal = computed(() => {
  if (mode.value === 'classic') {
    return subtotal(draft.value.rows.map(lineInput), 0)
  }

  const hours = draft.value.slider.hours ?? draft.value.slider.minHours ?? 0
  const rows = draft.value.slider.rows
    .filter((row) => !row.selectable || row.selected)
    .map((row) => ({ quantity: 1, unitPrice: eurosToCents(row.unitPrice) ?? 0, discountType: null, discountValue: null }))

  return subtotal(rows, hours * rate.value)
})

const positionsCount = computed(() => (mode.value === 'classic' ? draft.value.rows.length : draft.value.slider.rows.length))

const positionsSummary = computed(() => `${t('quotes.positionCount', positionsCount.value)} · ${formatEuros(positionsTotal.value)}`)

const termsSummary = computed(() => {
  const parts = draft.value.slider.stages.map((stage) => `${stage.percent} % ${stage.label}`.trim())

  if (draft.value.validUntil) {
    parts.push(t('quotes.validUntilInline', { date: monthDate(draft.value.validUntil) }))
  }

  return parts.join(' · ')
})

const textSummary = computed(() => draft.value.intro.split('\n')[0] ?? '')
</script>

<template>
  <div class="mx-auto flex w-fit max-w-full gap-6">
    <div class="flex w-[720px] max-w-full flex-col gap-3">
      <ChoiceGroup
        :model-value="mode"
        :options="[
          { value: 'classic', label: $t('quotes.modeClassic') },
          { value: 'choice', label: $t('quotes.modeChoice') },
        ]"
        variant="segmented"
        class="self-start"
        @update:model-value="setMode"
      />

      <QuoteCard v-model:open="open.recipient" :title="$t('quotes.recipient')" :summary="recipientSummary">
        <QuoteRecipientCard
          :quote-id="quoteId"
          :recipient-name="quote.recipientName"
          v-model:street="draft.recipientStreet"
          v-model:postcode="draft.recipientPostcode"
          v-model:city="draft.recipientCity"
          @change="emit('change')"
        />
      </QuoteCard>

      <QuoteCard v-if="mode === 'choice'" v-model:open="open.time" :title="$t('quotes.time')" :summary="timeSummary">
        <QuoteTimeCard
          v-model:time-title="draft.timeTitle"
          v-model:time-description="draft.timeDescription"
          v-model:slider="draft.slider"
          @change="emit('change')"
        />
      </QuoteCard>

      <QuoteCard v-model:open="open.positions" :title="$t('quotes.items')" :summary="positionsSummary">
        <QuotePositionsCard
          v-model="draft"
          :mode="mode"
          :quote-id="quoteId"
          :can-manage-catalog="canManageCatalog"
          :packages="packages"
          :catalog-items="catalogItems"
          @change="emit('change')"
        />
      </QuoteCard>

      <QuoteCard v-model:open="open.terms" :title="$t('quotes.terms')" :summary="termsSummary">
        <QuoteTermsCard
          :quote-id="quoteId"
          v-model:valid-until="draft.validUntil"
          v-model:stages="draft.slider.stages"
          @change="emit('change')"
        />
      </QuoteCard>

      <QuoteCard v-model:open="open.text" :title="$t('quotes.text')" :summary="textSummary">
        <div class="flex flex-col gap-4">
          <div class="flex min-w-0 flex-col gap-1.5">
            <Label>{{ $t('quotes.intro') }}</Label>
            <Textarea v-model="draft.intro" rows="3" @blur="emit('change')" />
          </div>
          <div class="flex min-w-0 flex-col gap-1.5">
            <Label>{{ $t('quotes.outro') }}</Label>
            <Textarea v-model="draft.outro" rows="3" @blur="emit('change')" />
          </div>
        </div>
      </QuoteCard>
    </div>

    <!-- Vorschau folgt in einer späteren Runde — hier steht nur ihr Platz. -->
    <div class="hidden w-[420px] flex-none min-[1180px]:block" />
  </div>
</template>
