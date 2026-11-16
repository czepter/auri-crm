<script setup lang="ts">
import { computed, reactive } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import type { PricingMode, QuoteContact, QuoteDraft, VatSettings } from './quotePayload'
import { quoteRowToLine } from './quotePayload'
import { subtotal } from '@/lib/quoteTotals'
import { eurosToCents, formatEuros } from '@/lib/money'
import { shortDate } from '@/portal/lib/dates'
import { ChoiceGroup } from '@/ui/choice-group'
import LetterField from '@/admin/components/quote/LetterField.vue'
import { Label } from '@/ui/label'
import DocumentPaper from '@/admin/components/document/DocumentPaper.vue'
import QuoteCard from './QuoteCard.vue'
import QuoteRecipientCard from './QuoteRecipientCard.vue'
import QuoteTimeCard from './QuoteTimeCard.vue'
import ShowcaseImages from '@/admin/components/quote/ShowcaseImages.vue'
import QuotePositionsCard from './QuotePositionsCard.vue'
import QuoteTermsCard from './QuoteTermsCard.vue'
import QuotePreviewPane from './QuotePreviewPane.vue'

const props = defineProps<{
  quoteId: string
  quote: App.Quotes.Data.QuoteData
  canManageCatalog: boolean
  contacts: QuoteContact[]
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
  vatSettings: VatSettings
  previewing?: boolean
}>()

const draft = defineModel<QuoteDraft>({ required: true })
const emit = defineEmits<{ change: [] }>()
const { t } = useI18n()

function setMode(value: string) {
  draft.value.pricingMode = value as PricingMode
  emit('change')
}

const brand = computed(() => (usePage().props.brand as string | null) ?? '')

const open = reactive({
  recipient: true,
  time: true,
  positions: true,
  terms: draft.value.pricingMode === 'classic',
  text: draft.value.pricingMode === 'classic',
})

const rate = computed(() => eurosToCents(draft.value.slider.hourlyRate) ?? 0)

const recipientName = computed(() => props.contacts.find((entry) => entry.id === draft.value.recipientContact)?.name ?? null)

const recipientSummary = computed(() =>
  [
    recipientName.value,
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
  const charged = draft.value.rows.filter((row) => !row.selectable || row.selected)
  const hours = draft.value.slider.hours ?? draft.value.slider.minHours ?? 0
  const timeTotal = draft.value.pricingMode === 'choice' ? hours * rate.value : 0

  return subtotal(charged.map(quoteRowToLine), timeTotal)
})

const positionsCount = computed(() => draft.value.rows.length)

const positionsSummary = computed(() => `${t('quotes.positionCount', positionsCount.value)} · ${formatEuros(positionsTotal.value)}`)

const termsSummary = computed(() => {
  const parts = draft.value.slider.stages.map((stage) => `${stage.fixed ? `${stage.minimum} €` : `${stage.percent} %`} ${stage.label}`.trim())

  if (draft.value.validUntil) {
    parts.push(t('quotes.validUntilInline', { date: shortDate(draft.value.validUntil) }))
  }

  return parts.join(' · ')
})

const textSummary = computed(() => new DOMParser().parseFromString(draft.value.intro, 'text/html').body.firstElementChild?.textContent ?? '')
</script>

<template>
  <div>
    <QuotePreviewPane v-if="previewing" class="mx-auto max-w-[900px]" :quote-id="quoteId" :draft="draft" :can-manage-catalog="canManageCatalog" :recipient-name="recipientName ?? ''" :vat-settings="vatSettings" />

    <DocumentPaper v-show="!previewing" :studio="brand" :kind="$t('quotes.paperKind')" :number="quote.number">
      <slot name="top" />

      <ChoiceGroup
        class="self-start"
        :model-value="draft.pricingMode"
        :options="[
          { value: 'classic', label: $t('quotes.modeClassic') },
          { value: 'choice', label: $t('quotes.modeChoice') },
        ]"
        @update:model-value="setMode"
      />

      <QuoteCard v-model:open="open.recipient" flat :title="$t('quotes.recipient')" :summary="recipientSummary">
        <QuoteRecipientCard
          :quote-id="quoteId"
          :contacts="contacts"
          :saved-street="quote.recipientStreet"
          :saved-postcode="quote.recipientPostcode"
          :saved-city="quote.recipientCity"
          v-model:contact="draft.recipientContact"
          v-model:street="draft.recipientStreet"
          v-model:postcode="draft.recipientPostcode"
          v-model:city="draft.recipientCity"
          @change="emit('change')"
        />
      </QuoteCard>

      <QuoteCard v-if="draft.pricingMode === 'choice'" v-model:open="open.time" flat :title="$t('quotes.time')" :summary="timeSummary">
        <QuoteTimeCard
          v-model:time-title="draft.timeTitle"
          v-model:time-description="draft.timeDescription"
          v-model:slider="draft.slider"
          @change="emit('change')"
        />

        <div class="mt-6">
          <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('quotes.showcase') }}</div>
          <ShowcaseImages :base="`/angebote/${quoteId}/schaufenster`" :count="quote.showcaseCount" />
          <span class="text-small leading-[1.45] font-normal text-faint">{{ $t('quotes.showcaseHint') }}</span>
        </div>
      </QuoteCard>

      <QuoteCard v-model:open="open.positions" flat :title="$t('quotes.items')" :summary="positionsSummary">
        <QuotePositionsCard
          v-model="draft"
          :mode="draft.pricingMode"
          :quote-id="quoteId"
          :can-manage-catalog="canManageCatalog"
          :packages="packages"
          :catalog-items="catalogItems"
          :vat-settings="vatSettings"
          @change="emit('change')"
        />
      </QuoteCard>

      <QuoteCard v-model:open="open.terms" flat :title="$t('quotes.terms')" :summary="termsSummary">
        <QuoteTermsCard
          :quote-id="quoteId"
          v-model:valid-until="draft.validUntil"
          v-model:stages="draft.slider.stages"
          @change="emit('change')"
        />
      </QuoteCard>

      <QuoteCard v-model:open="open.text" flat :title="$t('quotes.text')" :summary="textSummary">
        <div class="flex flex-col gap-4">
          <div class="flex min-w-0 flex-col gap-1.5">
            <Label>{{ $t('quotes.intro') }}</Label>
            <LetterField v-model="draft.intro" @update:model-value="emit('change')" />
          </div>
          <div class="flex min-w-0 flex-col gap-1.5">
            <Label>{{ $t('quotes.outro') }}</Label>
            <LetterField v-model="draft.outro" @update:model-value="emit('change')" />
          </div>
        </div>
      </QuoteCard>
    </DocumentPaper>
  </div>
</template>
