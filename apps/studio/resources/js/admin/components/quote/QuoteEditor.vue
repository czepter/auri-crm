<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { PricingMode, QuoteContact, QuoteDraft, VatSettings } from './quotePayload'
import { ChoiceGroup } from '@/ui/choice-group'
import LetterField from '@/admin/components/quote/LetterField.vue'
import { Field } from '@/ui/field'
import DocumentPaper from '@/admin/components/document/DocumentPaper.vue'
import QuoteRecipientCard from './QuoteRecipientCard.vue'
import QuoteTimeCard from './QuoteTimeCard.vue'
import ShowcaseImages from '@/admin/components/quote/ShowcaseImages.vue'
import QuotePositionsCard from './QuotePositionsCard.vue'
import QuoteTermsCard from './QuoteTermsCard.vue'
import ValidUntilField from './ValidUntilField.vue'
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

function setMode(value: string) {
  draft.value.pricingMode = value as PricingMode
  emit('change')
}

const brand = computed(() => (usePage().props.brand as string | null) ?? '')

const recipientName = computed(() => props.contacts.find((entry) => entry.id === draft.value.recipientContact)?.name ?? null)
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

      <div class="grid grid-cols-[minmax(0,1fr)_260px] items-start gap-8 max-[640px]:grid-cols-1 max-[640px]:gap-5">
        <Field :label="$t('quotes.recipient')">
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
        </Field>

        <ValidUntilField :id="`quote-valid-${quoteId}`" v-model="draft.validUntil" @blur.capture="emit('change')" />
      </div>

      <Field :label="$t('quotes.intro')">
        <LetterField v-model="draft.intro" @update:model-value="emit('change')" />
      </Field>

      <Field v-if="draft.pricingMode === 'choice'" :label="$t('quotes.time')">
        <QuoteTimeCard
          v-model:time-title="draft.timeTitle"
          v-model:time-description="draft.timeDescription"
          v-model:slider="draft.slider"
          @change="emit('change')"
        />

        <div class="mt-2">
          <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('quotes.showcase') }}</div>
          <ShowcaseImages :base="`/angebote/${quoteId}/schaufenster`" :count="quote.showcaseCount" />
          <span class="text-small leading-[1.45] font-normal text-faint">{{ $t('quotes.showcaseHint') }}</span>
        </div>
      </Field>

      <Field :label="$t('quotes.items')">
        <QuotePositionsCard
          v-model="draft"
          :mode="draft.pricingMode"
          :quote-id="quoteId"
          :can-manage-catalog="canManageCatalog"
          :packages="packages"
          :catalog-items="catalogItems"
          :vat-settings="vatSettings"
          @change="emit('change')"
        >
          <Field :label="$t('quotes.outro')">
            <LetterField v-model="draft.outro" @update:model-value="emit('change')" />
          </Field>
        </QuotePositionsCard>
      </Field>

      <Field :label="$t('quotes.payment')">
        <QuoteTermsCard v-model:stages="draft.slider.stages" @change="emit('change')" />
      </Field>
    </DocumentPaper>
  </div>
</template>
