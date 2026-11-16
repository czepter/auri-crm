<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import type { QuoteContact, VatSettings } from '@/admin/components/quote/quotePayload'
import { quoteTone, quoteTitle } from '@/admin/lib/quoteDisplay'
import Chip from '@/ui/page/Chip.vue'
import { ChoiceGroup } from '@/ui/choice-group'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import FormShell from '@/ui/page/FormShell.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'
import DocumentViews from '@/admin/components/DocumentViews.vue'
import QuoteActions from '@/admin/components/quote/QuoteActions.vue'
import QuoteWorkspace from '@/admin/components/quote/QuoteWorkspace.vue'
import { useI18n } from 'vue-i18n'
import { shortDate } from '@/portal/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  projectId: string
  projectName: string
  projectIsBooked: boolean
  canManageQuotes: boolean
  canManagePaymentPlan: boolean
  quotes: App.Quotes.Data.QuoteData[]
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
  contacts: QuoteContact[]
  canManageCatalog: boolean
  paymentPlan: App.Payments.Data.PaymentPlanData | null
  recipientName: string | null
  views: App.Documents.Data.DocumentViewData[]
  vatSettings: VatSettings
}>()
useLiveProps(['projectName', 'projectIsBooked', 'canManageQuotes', 'quotes', 'packages', 'catalogItems', 'contacts', 'canManageCatalog', 'recipientName', 'views', 'vatSettings'])

const { t } = useI18n()

const quote = computed(() => props.quotes[0])
const previewing = ref(false)
const workspace = ref<InstanceType<typeof QuoteWorkspace>>()

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const address = computed(() => {
  const quote = props.quotes[0]
  const line = [quote.recipientPostcode, quote.recipientCity].filter(Boolean).join(' ')

  return [quote.recipientStreet, line].filter(Boolean).join(', ')
})

// One line per fact this screen is the only place to see, not what the
// workspace card already shows (items, totals, status).
const facts = computed(() => {
  const quote = props.quotes[0]

  return [
    { label: t('quotes.recipient'), value: [props.recipientName, address.value].filter(Boolean).join(' · ') },
    { label: t('quotesOverview.number'), value: quote.number },
    { label: t('quotes.validUntil'), value: quote.validUntil ? shortDate(quote.validUntil) : null },
    {
      label: t('quotesOverview.createdAt'),
      value: quote.sentAt ?? quote.createdAt ? day(quote.sentAt ?? quote.createdAt!) : null,
    },
  ].filter((fact) => fact.value)
})
</script>

<template>
  <AdminLayout active="angebote">
    <FormShell
      v-if="quote.status === 'draft'"
      :kicker="recipientName ?? projectName" :kicker-href="`/auftraege/${projectId}`" :title="quoteTitle(quote, t)" :state="workspace?.state ?? 'saved'" wide
    >
      <template #status>
        <Chip :tone="quoteTone(quote.status)">{{ quote.statusLabel }}</Chip>
      </template>

      <template #head>
        <ChoiceGroup
          :model-value="previewing ? 'preview' : 'edit'"
          :options="[{ value: 'edit', label: $t('common.edit') }, { value: 'preview', label: $t('common.preview') }]"
          @update:model-value="(value) => (previewing = value === 'preview')"
        />
        <Button type="button" variant="outline" @click="workspace?.saveNow()">
          {{ $t('invoicesCreate.saveDraft') }}
        </Button>
        <div class="flex">
          <QuoteActions :project-id="projectId" :quote="quote" :can-manage-quotes="canManageQuotes" />
        </div>
      </template>

      <QuoteWorkspace
        ref="workspace"
        v-model:previewing="previewing"
        :show-header="false"
        :project-id="projectId"
        :can-manage-quotes="canManageQuotes"
        :can-manage-payment-plan="canManagePaymentPlan"
        :quotes="quotes"
        :packages="packages"
        :catalog-items="catalogItems"
        :contacts="contacts"
        :can-manage-catalog="canManageCatalog"
        :payment-plan="paymentPlan"
        :vat-settings="vatSettings"
      />
    </FormShell>

    <Page v-else data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="quoteTitle(quote, t)" :kicker="recipientName ?? projectName">
          <div class="ml-auto flex items-center gap-3.5 max-[640px]:ml-0 max-[640px]:w-full max-[640px]:flex-wrap max-[640px]:gap-y-2">
            <Chip :tone="quoteTone(quote.status)">{{ quote.statusLabel }}</Chip>
            <span v-if="quote.sentChannel" class="text-small text-ink-2">
              {{ quote.sentChannel === 'mail' ? $t('quotes.sentByMail') : $t('quotes.sentBySelf') }}
            </span>
            <Button :as="Link" variant="outline" :href="`/auftraege/${projectId}`">
              {{ projectIsBooked ? $t('quotes.toProject') : $t('quotes.toLead') }}
            </Button>
            <QuoteActions :project-id="projectId" :quote="quote" :can-manage-quotes="canManageQuotes" />
          </div>
        </PageHead>

        <dl v-if="facts.length" class="mx-8 mt-1 flex flex-wrap gap-x-6 gap-y-1">
          <div v-for="fact in facts" :key="fact.label" class="flex gap-1.5 text-small">
            <dt class="text-ink-2">{{ fact.label }}</dt>
            <dd class="m-0 font-medium">{{ fact.value }}</dd>
          </div>
        </dl>

        <div class="mx-8 mt-5">
          <QuoteWorkspace
            v-model:previewing="previewing"
            :show-header="false"
            :project-id="projectId"
            :can-manage-quotes="canManageQuotes"
            :can-manage-payment-plan="canManagePaymentPlan"
            :quotes="quotes"
            :packages="packages"
            :catalog-items="catalogItems"
            :contacts="contacts"
            :can-manage-catalog="canManageCatalog"
            :payment-plan="paymentPlan"
            :vat-settings="vatSettings"
          />
        </div>

        <section v-if="views.length" class="mx-8 mt-8">
          <h2 class="m-0 mb-2 text-body font-semibold">{{ $t('documentViews.title') }}</h2>
          <DocumentViews :views="views" />
        </section>
      </div>
    </Page>
  </AdminLayout>
</template>
