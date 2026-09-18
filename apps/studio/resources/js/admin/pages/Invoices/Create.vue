<script setup lang="ts">
import { computed, ref } from 'vue'
import { useMediaQuery } from '@vueuse/core'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import ContactTypeahead from '@/admin/components/ContactTypeahead.vue'
import ProjectTypeahead from '@/admin/components/ProjectTypeahead.vue'
import InvoicePreviewPane from '@/admin/components/InvoicePreviewPane.vue'
import LetterField from '@/admin/components/LetterField.vue'
import QuotePositionsCard from '@/admin/components/quote/QuotePositionsCard.vue'
import { buildQuotePayload, type QuoteDraft, type VatSettings } from '@/admin/components/quote/quotePayload'
import InvoiceDataGate from '@/admin/pages/Settings/panes/InvoiceDataGate.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { centsToEuros, formatEuros } from '@/admin/lib/money'

type Rate = { id: string; label: string; amount: number; dueOn: string }

const props = defineProps<{
  rates: Record<string, Rate[]>
  project: App.Crm.Data.ProjectSuggestionData | null
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
  vatSettings: VatSettings
  intro: string
  studioSettings: App.Settings.Data.StudioSettingsData
  missingInvoiceData: string[]
}>()

// IssueInvoice weist eine Rechnung ohne Steuernummer ohnehin ab; das Fenster
// steht vor dem Formular statt hinter dem Absenden.
const gateOpen = ref(props.missingInvoiceData.length > 0)

const project = ref(props.project)
const contact = ref<App.Crm.Data.ContactSuggestionData | null>(null)
const newContact = ref<{ name: string; email: string; street: string; postcode: string; city: string } | null>(null)
const dueOn = ref('')
const texts = ref({ intro: props.intro, outro: '' })

// The quote's own draft shape, so the quote's builder writes the positions;
// what only a quote has stays at its empty value.
const draft = ref<QuoteDraft>({
  pricingMode: 'classic',
  validUntil: '',
  discount: '',
  discountHidden: false,
  intro: '',
  outro: '',
  rows: [],
  slider: { hourlyRate: '', minHours: null, maxHours: null, imagesPerHour: null, hours: null, stages: [] },
  recipientContact: null,
  recipientStreet: null,
  recipientPostcode: null,
  recipientCity: null,
  timeTitle: null,
  timeDescription: null,
  timeSortOrder: 0,
})

const openRates = computed(() =>
  (project.value ? props.rates[project.value.id] ?? [] : [])
    .filter((rate) => !draft.value.rows.some((row) => row.installmentId === rate.id)),
)

// The row shows the rate's net at the studio's rate; the server bills the gross itself.
function pick(rate: Rate) {
  const net = props.vatSettings.mode === 'small_business'
    ? rate.amount
    : Math.floor((rate.amount * 10_000 + Math.floor((10_000 + props.vatSettings.rate) / 2)) / (10_000 + props.vatSettings.rate))

  draft.value.rows = [...draft.value.rows, {
    title: rate.label,
    description: '',
    quantity: 1,
    unitPrice: centsToEuros(net),
    discount: '',
    vatRate: null,
    selectable: false,
    selected: false,
    outcome: '',
    hasImage: false,
    installmentId: rate.id,
  }]
}

const payload = computed(() => {
  const quote = buildQuotePayload(draft.value, { canManageCatalog: false })

  return {
    contact_id: contact.value?.id,
    contact: newContact.value ?? undefined,
    project_id: project.value?.id,
    due_on: dueOn.value || null,
    discount_type: quote.discount_type,
    discount_value: quote.discount_value,
    items: quote.items.map((item, index) => ({ ...item, installment_id: draft.value.rows[index].installmentId })),
    ...texts.value,
  }
})

const form = useForm({})
const errors = computed(() => form.errors as Record<string, string | undefined>)
const hasFreeRows = computed(() => draft.value.rows.some((row) => !row.installmentId))

function submit() {
  form.transform(() => payload.value).post('/rechnungen')
}

const showPreview = useMediaQuery('(min-width: 1180px)')
</script>

<template>
  <AdminLayout active="rechnungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('invoicesCreate.title')" />

        <div class="flex gap-6 px-8 pt-5 pb-8">
          <form class="grid w-[720px] max-w-full flex-none content-start gap-4" @submit.prevent="submit">
            <Field :label="$t('invoicesCreate.project')">
              <ProjectTypeahead v-model="project" />
            </Field>

            <Field :label="$t('invoicesCreate.recipient')" :error="errors['contact.name'] ?? errors.contact_id">
              <div v-if="newContact" class="grid grid-cols-2 gap-3">
                <Input v-model="newContact.name" :placeholder="$t('invoicesCreate.name')" required />
                <Input v-model="newContact.email" type="email" :placeholder="$t('invoicesCreate.email')" />
                <Input v-model="newContact.street" class="col-span-2" :placeholder="$t('invoicesCreate.street')" />
                <Input v-model="newContact.postcode" :placeholder="$t('invoicesCreate.postcode')" />
                <Input v-model="newContact.city" :placeholder="$t('invoicesCreate.city')" />
              </div>
              <ContactTypeahead
                v-else
                v-model="contact"
                @create-new="(term) => (newContact = { name: term, email: '', street: '', postcode: '', city: '' })"
              />
            </Field>

            <Field v-if="openRates.length" :label="$t('invoicesCreate.rates')">
              <div class="flex flex-wrap gap-1.5">
                <Button v-for="rate in openRates" :key="rate.id" variant="outline" type="button" @click="pick(rate)">
                  + {{ rate.label }} · {{ formatEuros(rate.amount) }}
                </Button>
              </div>
            </Field>

            <Field :label="$t('quotes.items')" :error="errors.items">
              <QuotePositionsCard
                v-model="draft"
                mode="classic"
                quote-id=""
                :can-manage-catalog="false"
                :packages="packages"
                :catalog-items="catalogItems"
                :vat-settings="vatSettings"
              />
            </Field>

            <Field v-if="hasFreeRows" v-slot="{ id }" class="max-w-[220px]" :label="$t('payments.dueOn')" :error="errors.due_on">
              <DatePicker :id="id" v-model="dueOn" required />
            </Field>

            <Field :label="$t('quotes.intro')">
              <LetterField v-model="texts.intro" />
            </Field>
            <Field :label="$t('quotes.outro')">
              <LetterField v-model="texts.outro" />
            </Field>

            <p v-if="errors.invoice" class="text-[12px] text-destructive">{{ errors.invoice }}</p>

            <div>
              <Button type="submit" :disabled="!draft.rows.length || (!contact && !newContact && !project) || form.processing">
                {{ $t('invoicesCreate.submit') }}
              </Button>
            </div>
          </form>

          <div class="hidden min-w-[420px] flex-1 min-[1180px]:block">
            <InvoicePreviewPane v-if="showPreview" :payload="payload" />
          </div>
        </div>
      </div>
    </Page>

    <InvoiceDataGate
      :open="gateOpen"
      :settings="studioSettings"
      @done="gateOpen = false"
      @cancel="router.visit('/rechnungen')"
    />
  </AdminLayout>
</template>
