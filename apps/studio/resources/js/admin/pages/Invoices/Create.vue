<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import InvoiceTargetTypeahead from '@/admin/components/payments/InvoiceTargetTypeahead.vue'
import InvoicePreviewPane from '@/admin/components/payments/InvoicePreviewPane.vue'
import DocumentPaper from '@/admin/components/document/DocumentPaper.vue'
import LetterField from '@/admin/components/quote/LetterField.vue'
import QuotePositionsCard from '@/admin/components/quote/QuotePositionsCard.vue'
import { buildQuotePayload, type QuoteDraft, type VatSettings } from '@/admin/components/quote/quotePayload'
import InvoiceDataGate from '@/admin/pages/Settings/panes/InvoiceDataGate.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { ChoiceGroup } from '@/ui/choice-group'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { centsToEuros, formatEuros } from '@/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'

type Rate = { id: string; label: string; amount: number; dueOn: string }
type QuoteLine = { id: string; title: string; description: string | null; quantity: number; unitPrice: number; vatRate: number | null }

const props = defineProps<{
  rates: Record<string, Rate[]>
  quoteLines: Record<string, QuoteLine[]>
  billable: App.Crm.Data.CaseSuggestionData[]
  project: App.Crm.Data.CaseSuggestionData | null
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
  vatSettings: VatSettings
  intro: string
  studioSettings: App.Settings.Data.StudioSettingsData
  missingInvoiceData: string[]
  firstInvoice: boolean
}>()
useLiveProps([])

// IssueInvoice weist eine Rechnung ohne Steuernummer ohnehin ab; das Fenster
// steht vor dem Formular statt hinter dem Absenden.
const gateOpen = ref(props.missingInvoiceData.length > 0)

const project = ref(props.project)
const contact = ref<App.Crm.Data.ContactSuggestionData | null>(null)
const newContact = ref<{ name: string; email: string; street: string; postcode: string; city: string } | null>(null)
const inDays = (days: number) => {
  const date = new Date()
  date.setDate(date.getDate() + days)

  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}
const dueOn = ref(inDays(props.studioSettings.paymentTermDays))
const TERMS = [7, 14, 30]
const term = computed({
  get: () => String(TERMS.find((days) => inDays(days) === dueOn.value) ?? ''),
  set: (days: string) => { dueOn.value = inDays(Number(days)) },
})
const texts = ref({ intro: props.intro, outro: '' })
const previewing = ref(false)

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

const openLines = computed(() =>
  (project.value ? props.quoteLines[project.value.id] ?? [] : [])
    .filter((line) => !draft.value.rows.some((row) => row.quoteItemId === line.id)),
)

function takeLines() {
  draft.value.rows = [...draft.value.rows, ...openLines.value.map((line) => ({
    title: line.title,
    description: line.description ?? '',
    quantity: line.quantity,
    unitPrice: centsToEuros(line.unitPrice),
    discount: '',
    vatRate: line.vatRate,
    selectable: false,
    selected: false,
    outcome: '',
    hasImage: false,
    quoteItemId: line.id,
  }))]
}

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
    case_id: project.value?.id,
    due_on: dueOn.value || null,
    discount_type: quote.discount_type,
    discount_value: quote.discount_value,
    items: quote.items.map((item, index) => ({ ...item, installment_id: draft.value.rows[index].installmentId, quote_item_id: draft.value.rows[index].quoteItemId })),
    ...texts.value,
  }
})

const form = useForm({})
const errors = computed(() => form.errors as Record<string, string | undefined>)
const hasFreeRows = computed(() => draft.value.rows.some((row) => !row.installmentId))

function submit() {
  form.transform(() => payload.value).post('/rechnungen')
}

</script>

<template>
  <AdminLayout active="rechnungen">
    <FormShell :title="$t('invoicesCreate.title')" wide>
      <template #head>
        <ChoiceGroup
          class="ml-auto"
          :model-value="previewing ? 'preview' : 'edit'"
          :options="[{ value: 'edit', label: $t('common.edit') }, { value: 'preview', label: $t('common.preview') }]"
          @update:model-value="(value) => (previewing = value === 'preview')"
        />
        <Button type="submit" form="invoice" :disabled="!draft.rows.length || (!contact && !newContact && !project) || form.processing">
          {{ $t('invoicesCreate.submit') }}
        </Button>
      </template>

      <InvoicePreviewPane v-if="previewing" class="mx-auto max-w-[900px]" :payload="payload" />

      <DocumentPaper v-show="!previewing" :studio="studioSettings.displayName ?? ''" :kind="$t('invoicesCreate.paperKind')" :date="dayWithWeekday(inDays(0))">
        <form id="invoice" class="grid content-start gap-5" @submit.prevent="submit">
          <div class="grid grid-cols-[minmax(0,1fr)_260px] items-start gap-8 max-[640px]:grid-cols-1 max-[640px]:gap-5">
            <Field :label="$t('invoicesCreate.recipient')" :error="errors['contact.name'] ?? errors.contact_id">
              <div v-if="newContact" class="grid grid-cols-2 gap-3">
                <Input :aria-label="$t('invoicesCreate.name')" v-model="newContact.name" :placeholder="$t('invoicesCreate.name')" required />
                <Input :aria-label="$t('invoicesCreate.email')" v-model="newContact.email" type="email" :placeholder="$t('invoicesCreate.email')" />
                <Input :aria-label="$t('invoicesCreate.street')" v-model="newContact.street" class="col-span-2" :placeholder="$t('invoicesCreate.street')" />
                <Input :aria-label="$t('invoicesCreate.postcode')" v-model="newContact.postcode" :placeholder="$t('invoicesCreate.postcode')" />
                <Input :aria-label="$t('invoicesCreate.city')" v-model="newContact.city" :placeholder="$t('invoicesCreate.city')" />
                <Button class="col-span-2 justify-self-start" type="button" variant="secondary" @click="newContact = null">
                  {{ $t('common.edit') }}
                </Button>
              </div>
              <InvoiceTargetTypeahead
                v-else
                v-model:project="project"
                v-model:contact="contact"
                :rates="rates"
                :billable="billable"
                @create-new="(term) => (newContact = { name: term, email: '', street: '', postcode: '', city: '' })"
              />
            </Field>

            <div v-if="hasFreeRows || !draft.rows.length" class="grid gap-2">
              <Field v-slot="{ id }" :label="$t('payments.dueOn')" :error="errors.due_on">
                <DatePicker :id="id" v-model="dueOn" required />
              </Field>
              <ChoiceGroup v-model="term" :options="TERMS.map((days) => ({ value: String(days), label: $t('invoicesCreate.termDays', { days }) }))" />
            </div>
          </div>

          <Field v-if="openRates.length" :label="$t('invoicesCreate.rates')">
            <div class="flex flex-wrap gap-1.5">
              <Button v-for="rate in openRates" :key="rate.id" variant="outline" type="button" @click="pick(rate)">
                + {{ rate.label }} · {{ formatEuros(rate.amount) }}
              </Button>
            </div>
          </Field>

          <Button v-if="openLines.length" class="justify-self-start" variant="outline" type="button" @click="takeLines">
            {{ $t('invoicesCreate.fromQuote', openLines.length) }}
          </Button>

          <Field :label="$t('quotes.intro')">
            <LetterField v-model="texts.intro" bare />
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
            >
              <Field :label="$t('quotes.outro')">
                <LetterField v-model="texts.outro" />
              </Field>
            </QuotePositionsCard>
          </Field>

          <p v-if="errors.invoice" class="text-small text-destructive">{{ errors.invoice }}</p>
        </form>
      </DocumentPaper>
    </FormShell>

    <InvoiceDataGate
      :open="gateOpen"
      :first="firstInvoice"
      :missing="missingInvoiceData"
      :settings="studioSettings"
      @done="gateOpen = false"
      @cancel="router.visit('/rechnungen')"
    />
  </AdminLayout>
</template>
