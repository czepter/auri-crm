<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PricingMode, QuoteDraft } from './quotePayload'
import { quoteRowToLine } from './quotePayload'
import { subtotal } from './quoteTotals'
import { eurosToCents, formatEuros } from '@/admin/lib/money'
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

function setMode(value: string) {
  draft.value.pricingMode = value as PricingMode
  emit('change')
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

const positionsTotal = computed(() => {
  const charged =
    draft.value.pricingMode === 'classic' ? draft.value.rows : draft.value.rows.filter((row) => !row.selectable || row.selected)
  const hours = draft.value.slider.hours ?? draft.value.slider.minHours ?? 0
  const timeTotal = draft.value.pricingMode === 'choice' ? hours * rate.value : 0

  return subtotal(charged.map(quoteRowToLine), timeTotal)
})

const positionsCount = computed(() => draft.value.rows.length)

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
        :model-value="draft.pricingMode"
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

      <QuoteCard v-if="draft.pricingMode === 'choice'" v-model:open="open.time" :title="$t('quotes.time')" :summary="timeSummary">
        <QuoteTimeCard
          v-model:time-title="draft.timeTitle"
          v-model:time-description="draft.timeDescription"
          v-model:slider="draft.slider"
          :rows="draft.rows"
          @change="emit('change')"
        />
      </QuoteCard>

      <QuoteCard v-model:open="open.positions" :title="$t('quotes.items')" :summary="positionsSummary">
        <QuotePositionsCard
          v-model="draft"
          :mode="draft.pricingMode"
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
