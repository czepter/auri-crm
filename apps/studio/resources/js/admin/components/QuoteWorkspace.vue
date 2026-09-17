<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import QuoteEditor from '@/admin/components/quote/QuoteEditor.vue'
import QuotePreviewPane from '@/admin/components/quote/QuotePreviewPane.vue'
import Card from '@/ui/page/Card.vue'
import { debounced } from '@/admin/lib/autosave'
import { buildQuotePayload, type QuoteContact, type QuoteDraft, type VatSettings } from '@/admin/components/quote/quotePayload'
import { basisPointsToPercent, centsToEuros, eurosToCents, formatDiscount, formatEuros } from '@/admin/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'
import { quoteChipClass, quoteTitle } from '@/admin/lib/quoteDisplay'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { DatePicker } from '@/ui/date-picker'
import { Label } from '@/ui/label'
import { useI18n } from 'vue-i18n'
import QuoteActions from '@/admin/components/quote/QuoteActions.vue'
import InstallmentPaidDialog from '@/admin/components/InstallmentPaidDialog.vue'
import InstallmentStornoDialog from '@/admin/components/InstallmentStornoDialog.vue'
import CreditNoteDialog from '@/admin/components/CreditNoteDialog.vue'
import PaymentPlanDialog from '@/admin/components/PaymentPlanDialog.vue'

const props = withDefaults(
  defineProps<{
    projectId: string
    canManageQuotes: boolean
    canManagePaymentPlan: boolean
    quotes: App.Quotes.Data.QuoteData[]
    packages: App.Catalog.Data.PackageData[]
    catalogItems: App.Catalog.Data.CatalogItemData[]
    contacts: QuoteContact[]
    canManageCatalog: boolean
    paymentPlan: App.Payments.Data.PaymentPlanData | null
    vatSettings: VatSettings
    // The prop a partial reload asks for: the lead screen carries all of this
    // under one key.
    only?: string[]
    // False on a quote's own page: its PageHead already carries the title,
    // status and actions this header would repeat.
    showHeader?: boolean
  }>(),
  { only: () => ['quotes'], showHeader: true },
)

const { t } = useI18n()

function draftOf(quote: App.Quotes.Data.QuoteData): QuoteDraft {
  return {
    pricingMode: quote.pricingMode as QuoteDraft['pricingMode'],
    validUntil: quote.validUntil ?? '',
    discount: formatDiscount(quote.discountType, quote.discountValue),
    discountHidden: quote.discountHidden,
    intro: quote.intro ?? '',
    outro: quote.outro ?? '',
    recipientContact: quote.recipientContact,
    recipientStreet: quote.recipientStreet,
    recipientPostcode: quote.recipientPostcode,
    recipientCity: quote.recipientCity,
    timeTitle: quote.timeTitle,
    timeDescription: quote.timeDescription,
    timeSortOrder: quote.timeSortOrder,
    // Eine Liste für beide Modi (§4.2): Menge und Rabatt gelten überall,
    // "Im Portal" ist nur in Auswahl bedienbar, aber jede Zeile trägt es.
    rows: quote.items.map((item) => ({
      id: item.id,
      title: item.title,
      description: item.description ?? '',
      quantity: item.quantity,
      unitPrice: centsToEuros(item.unitPrice),
      discount: formatDiscount(item.discountType, item.discountValue),
      vatRate: item.vatRate,
      selectable: item.selectable,
      selected: item.selected,
      outcome: item.outcome ?? '',
      hasImage: item.hasImage,
    })),
    slider: {
      hourlyRate: centsToEuros(quote.hourlyRate),
      minHours: quote.minHours,
      maxHours: quote.maxHours,
      imagesPerHour: quote.imagesPerHour,
      hours: quote.hours,
      stages: quote.paymentStages.map((stage) => ({
        percent: stage.percent,
        label: stage.label,
        minimum: centsToEuros(stage.minimum) || '',
      })),
    },
  }
}

// Keyed by quote id and filled on demand rather than built once at setup: a
// partial reload that adds a quote used to leave the map behind.
const drafts = reactive<Record<string, QuoteDraft>>({})

watch(
  () => props.quotes,
  (quotes) => {
    for (const quote of quotes) {
      if (quote.status === 'draft' && !drafts[quote.id]) {
        drafts[quote.id] = draftOf(quote)
      }
    }
  },
  { immediate: true, deep: false },
)

// Ohne sie läuft jede weitere Taste in dieselbe 422 und das Getippte ist beim
// Neuladen weg.
const saveErrors = reactive<Record<string, string | null>>({})

const save = debounced((quoteId: string) => {
  const payload = buildQuotePayload(drafts[quoteId], { canManageCatalog: props.canManageCatalog })

  router.patch(`/angebote/${quoteId}`, payload, {
    // Die Zeile, in der getippt wird, überlebt die Antwort; zurück kommen nur
    // die serverseitig gerechneten Summen.
    preserveScroll: true,
    preserveState: true,
    only: props.only,
    onSuccess: () => (saveErrors[quoteId] = null),
    onError: (errors) => (saveErrors[quoteId] = Object.values(errors)[0] ?? null),
  })
}, 600)

function create() {
  router.post(`/auftraege/${props.projectId}/angebote`, {}, { preserveScroll: true })
}

const planForm = useForm({ total: '', installments: [{ due_on: '' }] })

// #480: a Gutschrift belongs to the invoice, and an invoice can carry several rates.
const creditNotes = computed(() => {
  const seen = new Set<string>()

  return (props.paymentPlan?.installments ?? []).flatMap((installment) => installment.creditNotes
    .filter((note) => !seen.has(note.id) && seen.add(note.id))
    .map((note) => ({ note, invoiceId: installment.invoiceId })))
})

function createPlan() {
  planForm
    .transform((data) => ({ ...data, total: eurosToCents(data.total) ?? 0 }))
    .post(`/auftraege/${props.projectId}/zahlungsplan`, { preserveScroll: true })
}

/*
 * Die Summenzeilen als Liste statt als Zweig je Fall: nur so weiss die
 * letzte Zeile, dass sie die letzte ist — im Mockup trägt allein sie die
 * Linie und das Gewicht. Q-04 setzt vatMode erst beim Versenden, ein
 * Entwurf endet deshalb bei Gesamt oder Netto.
 */
function summaryRows(quote: App.Quotes.Data.QuoteData) {
  const rows: { label: string; value: string; rate?: number }[] = quote.discountAmount
    ? [
        { label: 'quotes.total', value: formatEuros(quote.total) },
        { label: 'quotes.discount', value: `−${formatEuros(quote.discountAmount)}` },
        { label: 'quotes.net', value: formatEuros(quote.net) },
      ]
    : [{ label: 'quotes.total', value: formatEuros(quote.total) }]

  if (quote.vatMode === 'standard') {
    // #412: eine Zeile je Steuersatz, bei einem Satz genau die eine.
    for (const line of quote.vatLines) {
      rows.push({ label: 'quotes.vat', value: formatEuros(line.vat), rate: line.rate })
    }

    rows.push({ label: 'quotes.total', value: formatEuros(quote.net + (quote.vatAmount ?? 0)) })
  }

  return rows.map((row, index) => ({ ...row, last: index === rows.length - 1 }))
}

/** Zugeklappt trägt die Karte, was am Ende unter dem Strich steht. */
function totalOf(quote: App.Quotes.Data.QuoteData): string {
  const rows = summaryRows(quote)

  return rows[rows.length - 1].value
}
</script>

<template>
  <!-- Ein ersetztes Angebot rendert in derselben Schleife, statt versteckt zu
       werden oder zu 404en: Project::quotes() gibt jedes Angebot zurück, und
       dieser Abschnitt ist, was "bleibt erreichbar und liest sich als
       ersetzt" hier heisst. -->
  <template v-for="quote in quotes" :key="quote.id">
    <!-- Editing stays behind canManageQuotes and behind status === 'draft'
         — the UI half of Q-06's immutability rule, Quote::booted()'s guard
         being the half that still holds even if this condition were ever
         wrong or bypassed. Ein Entwurf ist das Werkzeug selbst und steht
         offen da; erst ab Versendet wird er zur zuklappbaren Karte. -->
    <section v-if="canManageQuotes && quote.status === 'draft' && drafts[quote.id]" class="mt-3 first:mt-0">
      <header v-if="showHeader" class="mb-3 flex items-center gap-3">
        <h2 class="m-0 text-[15px] leading-[1.2] font-semibold tracking-[-0.015em]">{{ quoteTitle(quote, t) }}</h2>
        <span :class="['chip', quoteChipClass(quote.status)]">{{ quote.statusLabel }}</span>
        <QuoteActions :project-id="projectId" :quote="quote" :can-manage-quotes="canManageQuotes" />
      </header>

      <QuoteEditor
        v-model="drafts[quote.id]"
        :quote-id="quote.id"
        :quote="quote"
        :can-manage-catalog="canManageCatalog"
        :packages="packages"
        :catalog-items="catalogItems"
        :contacts="contacts"
        :vat-settings="vatSettings"
        @change="save(quote.id)"
      />

      <p v-if="saveErrors[quote.id]" class="mt-2 text-[12px] text-destructive">{{ saveErrors[quote.id] }}</p>
    </section>

  <div v-else class="mt-3 flex gap-6 first:mt-0">
    <Card
      class="w-[640px] max-w-full flex-none"
      :default-open="quote.status !== 'superseded'"
      :summary="totalOf(quote)"
      :title="quoteTitle(quote, t)"
    >
      <template #action>
        <span :class="['chip', quoteChipClass(quote.status)]">{{ quote.statusLabel }}</span>
        <span v-if="quote.sentChannel" class="text-[11.5px] text-ink-2">
          {{ quote.sentChannel === 'mail' ? $t('quotes.sentByMail') : $t('quotes.sentBySelf') }}
        </span>

        <QuoteActions :project-id="projectId" :quote="quote" :can-manage-quotes="canManageQuotes" />
      </template>

        <p v-if="quote.intro" class="mb-3 text-[12.5px] leading-[1.55] whitespace-pre-line">{{ quote.intro }}</p>

        <div
          v-for="item in quote.items"
          :key="item.id"
          class="flex gap-3 py-[9px] text-[13px]"
          :class="item.isIncluded ? 'text-ink-2' : 'text-foreground'"
        >
          <span class="flex-1">
            {{ item.title }}
            <span v-if="item.description" class="mt-px block text-[11.5px] text-ink-2">
              {{ item.description }}
            </span>
          </span>
          <span class="w-[110px] flex-none text-right font-semibold tabular-nums">
            {{ item.isIncluded ? $t('quotes.includedPrice') : formatEuros(item.netTotal) }}
          </span>
        </div>

        <div
          v-for="(row, index) in summaryRows(quote)"
          :key="`${row.label}-${index}`"
          class="flex gap-3 py-1.5"
          :class="row.last ? 'mt-1.5 pt-2.5 font-semibold shadow-[inset_0_1px_0_var(--line)]' : 'text-[12.5px]'"
        >
          <span class="flex-1" :class="{ 'text-[13.5px]': row.last }">
            {{ row.rate === undefined ? $t(row.label) : $t('quotes.vat', { rate: basisPointsToPercent(row.rate) }) }}
          </span>
          <span class="w-[110px] flex-none text-right tabular-nums" :class="{ 'text-[14px]': row.last }">{{ row.value }}</span>
        </div>

        <!-- Q-04: nur ein bereits versendetes Angebot trägt den §19-Satz —
             ein Entwurf hat kein vatMode. -->
        <p v-if="quote.vatMode === 'small_business'" class="mt-1.5 text-[11.5px] leading-[1.45] text-ink-2">
          {{ quote.vatNote }}
        </p>

        <p v-if="quote.outro" class="mt-3 text-[12.5px] leading-[1.55] whitespace-pre-line">{{ quote.outro }}</p>
    </Card>

    <!-- Same 1180px column threshold QuoteEditor uses for its own preview
         column — QuotePreviewPane carries its own header (label, zoom
         toggle), so no Card wrapper here repeats "Vorschau" above it. -->
    <div v-if="quote.status !== 'draft'" class="hidden min-w-[420px] flex-1 rounded-md border border-rule bg-background px-[18px] py-[15px] min-[1180px]:block">
      <QuotePreviewPane :quote-id="quote.id" />
    </div>
  </div>
  </template>

  <Card
    v-if="paymentPlan || canManagePaymentPlan"
    class="mt-3"
    :title="$t('payments.title')"
    :summary="paymentPlan ? $t('payments.open', { amount: formatEuros(paymentPlan.openTotal) }) : null"
  >
    <template v-if="canManagePaymentPlan && paymentPlan && !paymentPlan.settledOn" #action>
      <PaymentPlanDialog :plan="paymentPlan" />
    </template>

    <div
      v-for="installment in paymentPlan?.installments ?? []"
      :key="installment.id"
      class="flex items-center gap-3 py-[9px] text-[13px]"
    >
      <span class="w-[110px] flex-none text-ink-2">{{ dayWithWeekday(installment.dueOn) }}</span>
      <span class="flex-1">{{ installment.label }}</span>
      <a
        v-if="installment.invoiceId && installment.hasInvoiceDocument"
        class="text-[12px] text-ink-2"
        :href="`/rechnungen/${installment.invoiceId}/pdf`"
        target="_blank"
      >{{ installment.invoiceNumber }}</a>
      <span v-else-if="installment.invoiceNumber" class="text-[12px] text-ink-2">{{ installment.invoiceNumber }}</span>
      <span class="text-[12px] text-ink-2">{{ installment.statusLabel }}</span>
      <span class="w-[110px] flex-none text-right font-semibold tabular-nums">{{ formatEuros(installment.amount) }}</span>
      <Button
        v-if="canManagePaymentPlan && !installment.invoiceId && installment.status !== 'cancelled'"
        variant="outline"
        type="button"
        @click="router.post(`/raten/${installment.id}/rechnung`)"
      >{{ $t('payments.issueInvoice') }}</Button>
      <InstallmentPaidDialog
        v-if="canManagePaymentPlan && installment.status !== 'cancelled' && installment.remaining > 0"
        :installment-id="installment.id"
        :remaining="installment.remaining"
      />
      <InstallmentStornoDialog
        v-if="canManagePaymentPlan && installment.invoiceId && installment.status === 'invoiced'"
        :invoice-id="installment.invoiceId"
      />
      <CreditNoteDialog
        v-if="canManagePaymentPlan && installment.invoiceId && installment.status === 'paid'"
        :invoice-id="installment.invoiceId"
        :amount="installment.amount"
      />
    </div>

    <div
      v-for="note in creditNotes"
      :key="note.note.id"
      class="flex items-center gap-3 py-[9px] text-[13px] text-ink-2"
    >
      <span class="w-[110px] flex-none">{{ dayWithWeekday(note.note.issuedOn) }}</span>
      <a
        v-if="note.note.hasDocument"
        class="flex-1"
        :href="`/rechnungen/${note.invoiceId}/gutschrift/${note.note.id}`"
        target="_blank"
      >{{ $t('payments.creditNoteRow', { number: note.note.number }) }}</a>
      <span v-else class="flex-1">{{ $t('payments.creditNoteRow', { number: note.note.number }) }}</span>
      <span class="w-[110px] flex-none text-right tabular-nums">−{{ formatEuros(note.note.amount) }}</span>
    </div>

    <form v-if="!paymentPlan && canManagePaymentPlan" class="mt-2 grid grid-cols-2 gap-x-5 gap-y-4" @submit.prevent="createPlan">
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label for="plan-total">{{ $t('payments.total') }}</Label>
        <Input id="plan-total" v-model="planForm.total" class="tabular-nums" type="text" inputmode="decimal" required />
      </div>

      <div v-for="(installment, index) in planForm.installments" :key="index" class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`plan-due-${index}`">{{ $t('payments.dueOn') }}</Label>
        <DatePicker :id="`plan-due-${index}`" v-model="installment.due_on" required />
      </div>

      <div class="col-span-2 flex items-center gap-1.5">
        <Button type="button" variant="outline" @click="planForm.installments.push({ due_on: '' })">
          {{ $t('payments.addInstallment') }}
        </Button>
        <Button type="submit" variant="pill" :disabled="planForm.processing">{{ $t('payments.create') }}</Button>
      </div>

      <p v-if="planForm.errors.installments" class="col-span-2 text-[12px] text-destructive">
        {{ planForm.errors.installments }}
      </p>
    </form>
  </Card>

  <p v-if="!quotes.length && !canManageQuotes" class="mt-3 text-[11.5px] leading-[1.45] text-faint">
    {{ $t('quotes.empty') }}
  </p>
</template>
