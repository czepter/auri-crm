<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useMediaQuery } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import type { PricingMode, QuoteContact, QuoteDraft, VatSettings } from './quotePayload'
import { quoteRowToLine } from './quotePayload'
import { subtotal } from './quoteTotals'
import { eurosToCents, formatEuros } from '@/admin/lib/money'
import { monthDate } from '@/admin/lib/dates'
import { ChoiceGroup } from '@/ui/choice-group'
import LetterField from '@/admin/components/LetterField.vue'
import { Label } from '@/ui/label'
import { Button } from '@/ui/button'
import { Dialog, DialogContent, DialogTitle, DialogTrigger } from '@/ui/dialog'
import QuoteCard from './QuoteCard.vue'
import QuoteRecipientCard from './QuoteRecipientCard.vue'
import QuoteTimeCard from './QuoteTimeCard.vue'
import ShowcaseImages from '@/admin/components/ShowcaseImages.vue'
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

const showPreviewColumn = useMediaQuery('(min-width: 1180px)')

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

const textSummary = computed(() => new DOMParser().parseFromString(draft.value.intro, 'text/html').body.firstElementChild?.textContent ?? '')
</script>

<template>
  <div class="flex w-fit max-w-full gap-6">
    <div class="flex w-[720px] max-w-full flex-col gap-3">
      <div class="flex items-center gap-2.5">
        <ChoiceGroup
          :model-value="draft.pricingMode"
          :options="[
            { value: 'classic', label: $t('quotes.modeClassic') },
            { value: 'choice', label: $t('quotes.modeChoice') },
          ]"
          variant="segmented"
          @update:model-value="setMode"
        />

        <Dialog>
          <DialogTrigger as-child>
            <Button type="button" variant="outline" class="ml-auto min-[1180px]:hidden">{{ $t('quotes.preview') }}</Button>
          </DialogTrigger>
          <DialogContent class="max-w-[860px]">
            <DialogTitle class="sr-only">{{ $t('quotes.preview') }}</DialogTitle>
            <QuotePreviewPane :quote-id="quoteId" :draft="draft" :can-manage-catalog="canManageCatalog" :recipient-name="recipientName ?? ''" :vat-settings="vatSettings" />
          </DialogContent>
        </Dialog>
      </div>

      <QuoteCard v-model:open="open.recipient" :title="$t('quotes.recipient')" :summary="recipientSummary">
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

      <QuoteCard v-if="draft.pricingMode === 'choice'" v-model:open="open.time" :title="$t('quotes.time')" :summary="timeSummary">
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

      <QuoteCard v-model:open="open.positions" :title="$t('quotes.items')" :summary="positionsSummary">
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
            <LetterField v-model="draft.intro" @update:model-value="emit('change')" />
          </div>
          <div class="flex min-w-0 flex-col gap-1.5">
            <Label>{{ $t('quotes.outro') }}</Label>
            <LetterField v-model="draft.outro" @update:model-value="emit('change')" />
          </div>
        </div>
      </QuoteCard>
    </div>

    <div class="hidden w-[420px] flex-none min-[1180px]:block min-[1440px]:w-[560px] min-[1700px]:w-[680px]">
      <QuotePreviewPane v-if="showPreviewColumn" :quote-id="quoteId" :draft="draft" :can-manage-catalog="canManageCatalog" :recipient-name="recipientName ?? ''" :vat-settings="vatSettings" />
    </div>
  </div>
</template>
