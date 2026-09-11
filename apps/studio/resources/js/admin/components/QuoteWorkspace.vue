<script setup lang="ts">
import { reactive, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import QuoteTable from '@/admin/components/QuoteTable.vue'
import QuoteSliderEditor from '@/admin/components/QuoteSliderEditor.vue'
import ValidUntilField from '@/admin/components/ValidUntilField.vue'
import Section from '@/ui/page/Section.vue'
import { debounced } from '@/admin/lib/autosave'
import { buildQuotePayload, type QuoteDraft } from '@/admin/components/quote/quotePayload'
import { basisPointsToPercent, centsToEuros, eurosToCents, formatDiscount, formatEuros } from '@/admin/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import { DatePicker } from '@/ui/date-picker'
import { Label } from '@/ui/label'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'

const props = withDefaults(
  defineProps<{
    projectId: string
    canManageQuotes: boolean
    canManagePaymentPlan: boolean
    quotes: App.Quotes.Data.QuoteData[]
    packages: App.Catalog.Data.PackageData[]
    catalogItems: App.Catalog.Data.CatalogItemData[]
    canManageCatalog: boolean
    paymentPlan: App.Payments.Data.PaymentPlanData | null
    // The prop a partial reload asks for: the lead screen carries all of this
    // under one key.
    only?: string[]
  }>(),
  { only: () => ['quotes'] },
)

function draftOf(quote: App.Quotes.Data.QuoteData): QuoteDraft {
  return {
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
    rows: quote.items.map((item) => ({
      id: item.id,
      title: item.title,
      description: item.description ?? '',
      quantity: item.quantity,
      unitPrice: centsToEuros(item.unitPrice),
      discount: formatDiscount(item.discountType, item.discountValue),
      // Abgeleitet statt gespeichert: gemerkt heisst, die Bibliothek führt
      // diesen Titel.
      remember: props.catalogItems.some((entry) => entry.title === item.title),
    })),
    slider: {
      hourlyRate: centsToEuros(quote.hourlyRate),
      minHours: quote.minHours,
      maxHours: quote.maxHours,
      imagesPerHour: quote.imagesPerHour,
      hours: quote.hours,
      rows: quote.items.map((item) => ({
        id: item.id,
        title: item.title,
        outcome: item.outcome ?? '',
        unitPrice: centsToEuros(item.unitPrice),
        selectable: item.selectable,
        selected: item.selected,
        hasImage: item.hasImage,
      })),
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

const packageForms = reactive<Record<string, ReturnType<typeof useForm<{ name: string }>>>>({})

watch(
  () => props.quotes,
  (quotes) => {
    for (const quote of quotes) {
      packageForms[quote.id] ??= useForm({ name: '' })
    }
  },
  { immediate: true },
)

function saveAsPackage(quoteId: string) {
  packageForms[quoteId].post(`/angebote/${quoteId}/als-paket`, {
    preserveScroll: true,
    onSuccess: () => packageForms[quoteId].reset(),
  })
}

const save = debounced((quoteId: string) => {
  const payload = buildQuotePayload(drafts[quoteId], { canManageCatalog: props.canManageCatalog })

  router.patch(`/angebote/${quoteId}`, payload, {
    // Die Zeile, in der getippt wird, überlebt die Antwort; zurück kommen nur
    // die serverseitig gerechneten Summen.
    preserveScroll: true,
    preserveState: true,
    only: props.only,
  })
}, 600)

function create() {
  router.post(`/auftraege/${props.projectId}/angebote`, {}, { preserveScroll: true })
}

function addendum(quoteId: string) {
  router.post(`/auftraege/${props.projectId}/angebote`, { addendum_to: quoteId }, { preserveScroll: true })
}

const planForm = useForm({ total: '', installments: [{ due_on: '' }] })

function createPlan() {
  planForm
    .transform((data) => ({ ...data, total: eurosToCents(data.total) ?? 0 }))
    .post(`/auftraege/${props.projectId}/zahlungsplan`, { preserveScroll: true })
}

function send(quoteId: string) {
  router.post(`/angebote/${quoteId}/versenden`, {}, { preserveScroll: true })
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
</script>

<template>
  <!-- Depth behind the Angebot & Vertrag tab: only the quote half exists so
       far — no tax breakdown beyond Q-04's own VAT row, no PDF link,
       Vertrag card, Nachtrag or payment plan yet (all Q-07 and later). A
       superseded quote renders in this same loop rather than being hidden
       or 404ing: Project::quotes() already returns every quote, and this
       section is what "stays reachable and reads as superseded" means
       here. -->
  <Section
    v-for="quote in quotes"
    :key="quote.id"
    :title="
      quote.isAddendum
        ? $t('quotes.addendumTo', { number: quote.predecessorNumber ?? '' })
        : quote.number
          ? `${$t('quotes.title')} ${quote.number}`
          : $t('quotes.title')
    "
  >
    <template #actions>
      <span :class="['chip', chipClass(quote.status)]">{{ quote.statusLabel }}</span>

      <div class="ml-auto flex items-center gap-1.5">
        <Button as="a" variant="outline" :href="`/angebote/${quote.id}/pdf`" target="_blank">
          {{ $t('quotes.pdf') }}
        </Button>

        <Button
          v-if="canManageQuotes && quote.status === 'accepted'"
          type="button"
          variant="outline"
          @click="addendum(quote.id)"
        >
          {{ $t('quotes.addendum') }}
        </Button>

        <Dialog v-if="canManageQuotes && packageForms[quote.id]">
          <DialogTrigger as-child>
            <Button variant="outline" type="button">{{ $t('quotes.saveAsPackage') }}</Button>
          </DialogTrigger>

          <DialogContent class="max-w-[420px]">
            <DialogHeader>
              <DialogTitle>{{ $t('quotes.saveAsPackage') }}</DialogTitle>
            </DialogHeader>

            <DialogBody>
              <form
                :id="`as-package-${quote.id}`"
                class="grid grid-cols-2 gap-x-5 gap-y-4"
                @submit.prevent="saveAsPackage(quote.id)"
              >
                <div class="col-span-2 flex min-w-0 flex-col gap-1.5">
                  <Label :for="`package-name-${quote.id}`">{{ $t('quotes.packageName') }}</Label>
                  <Input
                    :id="`package-name-${quote.id}`"
                    v-model="packageForms[quote.id].name"
                    type="text"
                    required
                  />
                  <p v-if="packageForms[quote.id].errors.name" class="text-[12px] text-destructive">
                    {{ packageForms[quote.id].errors.name }}
                  </p>
                </div>
              </form>
            </DialogBody>

            <DialogFooter>
              <Button type="submit" :form="`as-package-${quote.id}`" :disabled="packageForms[quote.id].processing">
                {{ $t('common.save') }}
              </Button>
              <DialogClose as-child>
                <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
              </DialogClose>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <Button
          v-if="canManageQuotes && quote.status === 'draft'"
          type="button"
          variant="pill"
          @click="send(quote.id)"
        >
          {{ $t('quotes.send') }}
        </Button>
      </div>
    </template>

    <!-- Editing stays behind canManageQuotes and behind status === 'draft'
         — the UI half of Q-06's immutability rule, Quote::booted()'s guard
         being the half that still holds even if this condition were ever
         wrong or bypassed. Ein Entwurf ist die Tabelle; erst ab Versendet
         tritt das gelesene Dokument an ihre Stelle. -->
    <template v-if="canManageQuotes && quote.status === 'draft' && drafts[quote.id]">
      <!-- Q-14: der Stundensatz ist die Weiche. Leer bleibt die Tabelle
           stehen, die vor dieser Runde hier stand. -->
      <QuoteSliderEditor
        v-model="drafts[quote.id].slider"
        :quote-id="quote.id"
        @change="save(quote.id)"
      />

      <QuoteTable
        v-if="!drafts[quote.id].slider.hourlyRate"
        class="mt-8"
        v-model="drafts[quote.id].rows"
        :can-manage-catalog="canManageCatalog"
        :packages="packages"
        :catalog-items="catalogItems"
        @change="save(quote.id)"
      />

      <div class="mt-4 grid grid-cols-2 gap-x-5 gap-y-4">
        <ValidUntilField
          :id="`quote-valid-${quote.id}`"
          v-model="drafts[quote.id].validUntil"
          @blur.capture="save(quote.id)"
        />
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label :for="`quote-discount-${quote.id}`">{{ $t('quotes.discount') }}</Label>
          <Input
            :id="`quote-discount-${quote.id}`"
            v-model="drafts[quote.id].discount"
            class="tabular-nums"
            type="text"
            inputmode="decimal"
            @blur="save(quote.id)"
          />
        </div>
        <div class="col-span-2 flex min-w-0 flex-col gap-1.5">
          <Label :for="`quote-intro-${quote.id}`">{{ $t('quotes.intro') }}</Label>
          <Textarea :id="`quote-intro-${quote.id}`" v-model="drafts[quote.id].intro" rows="3" @blur="save(quote.id)" />
        </div>
        <div class="col-span-2 flex min-w-0 flex-col gap-1.5">
          <Label :for="`quote-outro-${quote.id}`">{{ $t('quotes.outro') }}</Label>
          <Textarea :id="`quote-outro-${quote.id}`" v-model="drafts[quote.id].outro" rows="3" @blur="save(quote.id)" />
        </div>
      </div>
    </template>

    <template v-else>
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
          {{ item.isIncluded ? $t('quotes.includedPrice') : formatEuros(item.lineTotal) }}
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
    </template>
  </Section>

  <Section v-if="paymentPlan || canManagePaymentPlan" :title="$t('payments.title')">
    <template v-if="paymentPlan" #actions>
      <span class="text-[12px] text-ink-2">{{ $t('payments.open', { amount: formatEuros(paymentPlan.openTotal) }) }}</span>
    </template>

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
  </Section>

  <p v-if="!quotes.length && !canManageQuotes" class="mt-10 text-[11.5px] leading-[1.45] text-faint">
    {{ $t('quotes.empty') }}
  </p>
</template>
