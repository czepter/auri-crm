<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import type { QuoteContact, VatSettings } from '@/admin/components/quote/quotePayload'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import QuoteWorkspace from '@/admin/components/QuoteWorkspace.vue'
import { Button } from '@/ui/button'

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  quotes: App.Quotes.Data.QuoteData[]
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
  contacts: QuoteContact[]
  canManageCatalog: boolean
  paymentPlan: App.Payments.Data.PaymentPlanData | null
  vatSettings: VatSettings
}>()

function create() {
  router.post(`/auftraege/${props.header.id}/angebote`, {}, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectShell :header="header" active="quotes">
      <template v-if="header.canManageQuotes" #actions>
        <Button type="button" variant="pill" @click="create">
          <svg class="size-3" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
          {{ $t('quotes.create') }}
        </Button>
      </template>

      <QuoteWorkspace
        :project-id="header.id"
        :can-manage-quotes="header.canManageQuotes"
        :can-manage-payment-plan="header.canManagePaymentPlan"
        :quotes="quotes"
        :packages="packages"
        :catalog-items="catalogItems"
        :contacts="contacts"
        :can-manage-catalog="canManageCatalog"
        :payment-plan="paymentPlan"
        :vat-settings="vatSettings"
      />
    </ProjectShell>
  </AdminLayout>
</template>
