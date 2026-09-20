<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import type { QuoteContact, VatSettings } from '@/admin/components/quote/quotePayload'
import { quoteChipClass, quoteTitle } from '@/admin/lib/quoteDisplay'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'
import QuoteActions from '@/admin/components/quote/QuoteActions.vue'
import QuoteWorkspace from '@/admin/components/QuoteWorkspace.vue'
import { useI18n } from 'vue-i18n'

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
  vatSettings: VatSettings
}>()

const { t } = useI18n()

const quote = computed(() => props.quotes[0])

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
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
    { label: t('quotes.validUntil'), value: quote.validUntil },
    {
      label: t('quotesOverview.createdAt'),
      value: quote.sentAt ? `${day(quote.sentAt)} (${quote.sentChannel === 'mail' ? t('quotes.sentByMail') : t('quotes.sentBySelf')})` : null,
    },
  ].filter((fact) => fact.value)
})
</script>

<template>
  <AdminLayout active="angebote">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="quoteTitle(quote, t)" :kicker="recipientName ?? projectName">
          <div class="ml-auto flex items-center gap-3.5">
            <span :class="['chip', quoteChipClass(quote.status)]">{{ quote.statusLabel }}</span>
            <span v-if="quote.sentChannel" class="text-[11.5px] text-ink-2">
              {{ quote.sentChannel === 'mail' ? $t('quotes.sentByMail') : $t('quotes.sentBySelf') }}
            </span>
            <Button :as="Link" variant="outline" :href="`/auftraege/${projectId}`">
              {{ projectIsBooked ? $t('quotes.toProject') : $t('quotes.toLead') }}
            </Button>
            <QuoteActions :project-id="projectId" :quote="quote" :can-manage-quotes="canManageQuotes" />
          </div>
        </PageHead>

        <dl v-if="facts.length" class="mx-8 mt-1 flex flex-wrap gap-x-6 gap-y-1">
          <div v-for="fact in facts" :key="fact.label" class="flex gap-1.5 text-[12.5px]">
            <dt class="text-ink-2">{{ fact.label }}</dt>
            <dd class="m-0 font-medium">{{ fact.value }}</dd>
          </div>
        </dl>

        <div class="mx-8 mt-5">
          <QuoteWorkspace
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
      </div>
    </Page>
  </AdminLayout>
</template>
