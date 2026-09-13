<script setup lang="ts">
import { reactive, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import QuoteEditor from '@/admin/components/quote/QuoteEditor.vue'
import Card from '@/ui/page/Card.vue'
import { debounced } from '@/admin/lib/autosave'
import { buildQuotePayload, type QuoteContact, type QuoteDraft } from '@/admin/components/quote/quotePayload'
import { basisPointsToPercent, centsToEuros, eurosToCents, formatDiscount, formatEuros } from '@/admin/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { DatePicker } from '@/ui/date-picker'
import { Label } from '@/ui/label'
import { useI18n } from 'vue-i18n'
import QuoteActions from '@/admin/components/quote/QuoteActions.vue'

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
    // The prop a partial reload asks for: the lead screen carries all of this
    // under one key.
    only?: string[]
  }>(),
  { only: () => ['quotes'] },
)

const { t } = useI18n()

function draftOf(quote: App.Quotes.Data.QuoteData): QuoteDraft {
  return {
    pricingMode: quote.pricingMode as QuoteDraft['pricingMode'],
    validUntil: quote.validUntil ?? '',
    discount: formatDiscount(quote.discountType, quote.discountValue),
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

function createPlan() {
  planForm
    .transform((data) => ({ ...data, total: eurosToCents(data.total) ?? 0 }))
    .post(`/auftraege/${props.projectId}/zahlungsplan`, { preserveScroll: true })
}

function titleOf(quote: App.Quotes.Data.QuoteData): string {
  if (quote.isAddendum) {
    return t('quotes.addendumTo', { number: quote.predecessorNumber ?? '' })
  }

  return quote.number ? `${t('quotes.title')} ${quote.number}` : t('quotes.title')
}

/** draft/sent/accepted/superseded/expired -> the mockup's own three chip states (tokens.css). */
function chipClass(status: string): string {
  return ({ sent: 'now', accepted: 'done' } as Record<string, string>)[status] ?? 'next'
}

/*
 * Die Summenzeilen als Liste statt als Zweig je Fall: nur so weiss die
 * letzte Zeile, dass sie die letzte ist — im Mockup trägt allein sie die
 * Linie und das Gewicht. Q-04 setzt vatMode erst beim Versenden, ein
 * Entwurf endet deshalb bei Gesamt oder Netto.
 */
function summaryRows(quote: App.Quotes.Data.QuoteData) {
  const rows = quote.discountAmount
    ? [
        { label: 'quotes.total', value: formatEuros(quote.total) },
        { label: 'quotes.discount', value: `−${formatEuros(quote.discountAmount)}` },
        { label: 'quotes.net', value: formatEuros(quote.net) },
      ]
    : [{ label: 'quotes.total', value: formatEuros(quote.total) }]

  if (quote.vatMode === 'standard') {
    rows.push(
      { label: 'quotes.vat', value: formatEuros(quote.vatAmount ?? 0) },
      { label: 'quotes.total', value: formatEuros(quote.net + (quote.vatAmount ?? 0)) },
    )
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
      <header class="mb-3 flex items-center gap-3">
        <h2 class="m-0 text-[15px] leading-[1.2] font-semibold tracking-[-0.015em]">{{ titleOf(quote) }}</h2>
        <span :class="['chip', chipClass(quote.status)]">{{ quote.statusLabel }}</span>
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
        @change="save(quote.id)"
      />

      <p v-if="saveErrors[quote.id]" class="mt-2 text-[12px] text-destructive">{{ saveErrors[quote.id] }}</p>
    </section>

  <Card
    v-else
    class="mt-3 first:mt-0"
    :default-open="quote.status !== 'superseded'"
    :summary="totalOf(quote)"
    :title="titleOf(quote)"
  >
    <template #action>
      <span :class="['chip', chipClass(quote.status)]">{{ quote.statusLabel }}</span>
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
          <span v-if="item.isIncluded || item.description" class="mt-px block text-[11.5px] text-ink-2">
            {{ item.isIncluded ? $t('quotes.included') : item.description }}
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
          {{ row.label === 'quotes.vat' ? $t('quotes.vat', { rate: basisPointsToPercent(quote.vatRate) }) : $t(row.label) }}
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
  </template>

  <Card
    v-if="paymentPlan || canManagePaymentPlan"
    class="mt-3"
    :title="$t('payments.title')"
    :summary="paymentPlan ? $t('payments.open', { amount: formatEuros(paymentPlan.openTotal) }) : null"
  >
    <div
      v-for="installment in paymentPlan?.installments ?? []"
      :key="installment.id"
      class="flex gap-3 py-[9px] text-[13px]"
    >
      <span class="w-[110px] flex-none text-ink-2">{{ dayWithWeekday(installment.dueOn) }}</span>
      <span class="flex-1">{{ installment.label }}</span>
      <span class="text-[12px] text-ink-2">{{ installment.statusLabel }}</span>
      <span class="w-[110px] flex-none text-right font-semibold tabular-nums">{{ formatEuros(installment.amount) }}</span>
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
