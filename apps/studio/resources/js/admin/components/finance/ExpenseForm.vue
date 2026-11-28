<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Chip from '@/ui/page/Chip.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { DatePicker } from '@/ui/date-picker'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Switch } from '@/ui/switch'
import { Textarea } from '@/ui/textarea'
import AssetFields, { type AssetForm } from '@/admin/components/finance/AssetFields.vue'
import ReceiptPane from '@/admin/components/finance/ReceiptPane.vue'
import DraftDeleteDialog from '@/admin/components/DraftDeleteDialog.vue'
import { basisPointsToPercent, formatEuros, formatPercent, percentToBasisPoints } from '@/lib/money'
import { centsToField, costBasis, defaultMethod, modeForYear, parseCents, splitGross } from '@/admin/lib/finance'
import { dayWithWeekday, isoDate } from '@/admin/lib/dates'

type Expense = App.Finance.Data.ExpenseDetailData

const props = defineProps<{
  expense: Expense | null
  locked: boolean
  categories: App.Finance.Data.ExpenseCategoryData[]
  gwgLimit: number
  afaCategories: { key: string; label: string; months: number }[]
  vatModes: Record<string, string>
  defaultVatMode: string
}>()

const { t } = useI18n()
const e = props.expense
const cents = (value: string) => parseCents(value) ?? 0
const field = (value: number | null | undefined) => (value == null ? '' : centsToField(value))

const documents = ref<App.Finance.Data.FinanceDocumentData[]>(e?.documents ?? [])
const deleteUrl = ref<string | null>(null)

const form = useForm({
  vendor: e?.vendor ?? '',
  description: e?.description ?? '',
  invoice_no: e?.invoiceNo ?? '',
  invoice_date: e?.invoiceDate ?? '',
  paid_at: e?.paidAt ?? '',
  net: field(e?.net),
  vat_rate: e ? basisPointsToPercent(e.vatRate) : props.defaultVatMode === 'small_business' ? '0' : '19',
  vat: field(e?.vat),
  gross: field(e?.gross),
  currency: e?.currency ?? 'EUR',
  gross_eur: field(e?.grossEur),
  category_id: e?.categoryId ?? '',
  business_share: String(e?.businessShare ?? 100),
  note: e?.note ?? '',
  is_self_issued: e?.isSelfIssued ?? false,
})

const foreign = computed(() => form.currency.trim().toUpperCase() !== 'EUR')

// The input event fires before v-model has stored the typed value, so the handlers take it from the event.
const typed = (event: Event) => (event.target as HTMLInputElement).value

function fromGross(gross: string, rate: string) {
  const split = splitGross(cents(gross), percentToBasisPoints(rate) ?? 0)
  form.net = centsToField(split.net)
  form.vat = centsToField(split.vat)
  nextTick(syncAsset)
}

function fromParts(net: string, vat: string) {
  form.gross = centsToField(cents(net) + cents(vat))
  nextTick(syncAsset)
}

const asset = ref<AssetForm | null>(
  e?.asset
    ? {
        name: e.asset.name,
        acquired_at: e.asset.acquiredAt,
        cost_net: centsToField(e.asset.costNet),
        cost_gross: centsToField(e.asset.costGross),
        method: e.asset.method,
        useful_life_months: e.asset.usefulLifeMonths ? String(e.asset.usefulLifeMonths) : '',
        afa_category: e.asset.afaCategory ?? '',
      }
    : null,
)
let costsTouched = !!e?.asset
let methodTouched = !!e?.asset

const assetOn = computed({
  get: () => asset.value !== null,
  set: (on: boolean) => {
    if (!on) {
      asset.value = null

      return
    }

    costsTouched = methodTouched = false
    asset.value = {
      name: form.description || form.vendor,
      acquired_at: form.invoice_date || form.paid_at || isoDate(new Date()),
      cost_net: '',
      cost_gross: '',
      method: 'linear',
      useful_life_months: '',
      afa_category: '',
    }
    syncAsset()
  },
})

function syncAsset() {
  const current = asset.value

  if (!current) return

  if (!costsTouched) {
    current.cost_net = foreign.value ? form.gross_eur : form.net
    current.cost_gross = foreign.value ? form.gross_eur : form.gross
  }

  if (!methodTouched) {
    const year = Number(current.acquired_at.slice(0, 4)) || new Date().getFullYear()
    current.method = defaultMethod(costBasis(modeForYear(props.vatModes, props.defaultVatMode, year), cents(current.cost_net), cents(current.cost_gross)))
  }
}

const lockedRows = computed(() => {
  if (!e) return []

  const eur = (cents: number | null) => (cents === null ? '' : formatEuros(cents))
  const rows: { label: string; value: string; numeric?: boolean }[] = [
    { label: t('financeExpense.vendor'), value: e.vendor ?? '' },
    { label: t('financeExpense.description'), value: e.description ?? '' },
    { label: t('financeExpense.invoiceNo'), value: e.invoiceNo ?? '' },
    { label: t('financeExpense.invoiceDate'), value: dayWithWeekday(e.invoiceDate, '') ?? '' },
    { label: t('financeExpense.paidAt'), value: dayWithWeekday(e.paidAt, '') ?? '' },
    { label: t('financeExpense.net'), value: eur(e.net), numeric: true },
    { label: t('financeExpense.vat'), value: `${eur(e.vat)} · ${formatPercent(e.vatRate)} %`, numeric: true },
    { label: t('financeExpense.gross'), value: `${eur(e.gross)}${e.currency !== 'EUR' ? ` ${e.currency}` : ''}`, numeric: true },
    ...(e.currency !== 'EUR' ? [{ label: t('financeExpense.grossEur'), value: eur(e.grossEur), numeric: true }] : []),
    { label: t('financeExpense.category'), value: props.categories.find((c) => c.id === e.categoryId)?.label ?? '' },
    { label: t('financeExpense.share'), value: `${e.businessShare} %`, numeric: true },
    { label: t('financeExpense.note'), value: e.note ?? '' },
    ...(e.asset
      ? [
          { label: t('financeExpense.inventoryNo'), value: e.asset.inventoryNo, numeric: true },
          { label: t('financeExpense.assetName'), value: e.asset.name },
          { label: t('financeExpense.method'), value: e.asset.method === 'gwg' ? t('financeExpense.gwg') : `${t('financeExpense.linear')} · ${e.asset.usefulLifeMonths} ${t('common.unitMonths')}` },
        ]
      : []),
  ]

  return rows.filter((row) => row.value !== '')
})

const errors = computed(() => form.errors as Record<string, string>)
const status = computed(() => e?.status ?? 'draft')

function submit(book: boolean) {
  form.clearErrors()
  form
    .transform((data) => ({
      ...data,
      net: cents(data.net),
      vat_rate: percentToBasisPoints(data.vat_rate) ?? 0,
      vat: cents(data.vat),
      gross: cents(data.gross),
      currency: data.currency.trim().toUpperCase(),
      gross_eur: parseCents(data.gross_eur),
      category_id: data.category_id || null,
      business_share: Number(data.business_share),
      document_ids: documents.value.map((doc) => doc.id),
      asset: asset.value && {
        ...asset.value,
        cost_net: cents(asset.value.cost_net),
        cost_gross: cents(asset.value.cost_gross),
        useful_life_months: asset.value.useful_life_months ? Number(asset.value.useful_life_months) : null,
        afa_category: asset.value.afa_category || null,
      },
      book,
    }))
    [e ? 'put' : 'post'](e ? `/finanzen/ausgaben/${e.id}` : '/finanzen/ausgaben', { preserveScroll: true })
}

function setStatus(next: 'draft' | 'ignored') {
  router.post(`/finanzen/ausgaben/${e?.id}/status`, { status: next }, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="ausgaben">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="e ? $t('financeExpense.title') : $t('financeExpense.titleNew')" :kicker="$t('financeExpenses.title')" kicker-href="/finanzen/ausgaben">
          <Chip v-if="e" :tone="status === 'booked' ? 'success' : status === 'draft' ? 'warn' : 'neutral'">{{ $t(`financeExpenses.${status}`) }}</Chip>
          <Chip v-if="locked">{{ $t('financeExpenses.locked') }}</Chip>
        </PageHead>

        <div class="grid max-w-[1280px] items-start gap-10 px-6 pt-6 pb-10 max-[640px]:px-5 min-[1000px]:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
          <div class="min-w-0 min-[1000px]:sticky min-[1000px]:top-6">
            <ReceiptPane v-model:documents="documents" :expense-id="e?.id" :readonly="locked || status === 'ignored'" />
          </div>

          <dl v-if="locked && e" class="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2.5 text-ui">
            <template v-for="row in lockedRows" :key="row.label">
              <dt class="text-ink-2">{{ row.label }}</dt><dd class="whitespace-pre-line" :class="row.numeric && 'tabular-nums'">{{ row.value }}</dd>
            </template>
          </dl>

          <form v-else class="grid min-w-0 gap-4" @submit.prevent="submit(false)">
            <fieldset :disabled="status === 'ignored'" class="contents">
            <Field v-slot="{ id }" :label="$t('financeExpense.vendor')" :error="errors.vendor">
              <Input :id="id" v-model="form.vendor" type="text" maxlength="200" />
            </Field>
            <Field v-slot="{ id }" :label="$t('financeExpense.description')" :error="errors.description">
              <Input :id="id" v-model="form.description" type="text" />
            </Field>

            <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
              <Field v-slot="{ id }" :label="$t('financeExpense.invoiceNo')" :error="errors.invoice_no">
                <Input :id="id" v-model="form.invoice_no" type="text" maxlength="100" />
              </Field>
              <Field v-slot="{ id }" :label="$t('financeExpense.invoiceDate')" :error="errors.invoice_date">
                <DatePicker :id="id" v-model="form.invoice_date" />
              </Field>
              <Field v-slot="{ id }" :label="$t('financeExpense.paidAt')" :error="errors.paid_at">
                <DatePicker :id="id" v-model="form.paid_at" />
              </Field>
              <Field v-slot="{ id }" :label="$t('financeExpense.currency')" :error="errors.currency">
                <Input :id="id" v-model="form.currency" type="text" maxlength="3" class="uppercase" @input="nextTick(syncAsset)" />
              </Field>
            </div>

            <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
              <Field v-slot="{ id }" :label="$t('financeExpense.gross')" :error="errors.gross">
                <InputGroup>
                  <InputGroupInput :id="id" v-model="form.gross" class="tabular-nums" type="text" inputmode="decimal" @input="fromGross(typed($event), form.vat_rate)" />
                  <InputGroupAddon align="inline-end">{{ form.currency.toUpperCase() }}</InputGroupAddon>
                </InputGroup>
              </Field>
              <Field v-slot="{ id }" :label="$t('financeExpense.vatRate')" :error="errors.vat_rate">
                <InputGroup>
                  <InputGroupInput :id="id" v-model="form.vat_rate" class="tabular-nums" type="text" inputmode="decimal" @input="fromGross(form.gross, typed($event))" />
                  <InputGroupAddon align="inline-end">%</InputGroupAddon>
                </InputGroup>
              </Field>
              <Field v-slot="{ id }" :label="$t('financeExpense.net')" :error="errors.net">
                <InputGroup>
                  <InputGroupInput :id="id" v-model="form.net" class="tabular-nums" type="text" inputmode="decimal" @input="fromParts(typed($event), form.vat)" />
                  <InputGroupAddon align="inline-end">{{ form.currency.toUpperCase() }}</InputGroupAddon>
                </InputGroup>
              </Field>
              <Field v-slot="{ id }" :label="$t('financeExpense.vat')" :error="errors.vat">
                <InputGroup>
                  <InputGroupInput :id="id" v-model="form.vat" class="tabular-nums" type="text" inputmode="decimal" @input="fromParts(form.net, typed($event))" />
                  <InputGroupAddon align="inline-end">{{ form.currency.toUpperCase() }}</InputGroupAddon>
                </InputGroup>
              </Field>
              <Field v-if="foreign" v-slot="{ id }" :label="$t('financeExpense.grossEur')" :error="errors.gross_eur">
                <InputGroup>
                  <InputGroupInput :id="id" v-model="form.gross_eur" class="tabular-nums" type="text" inputmode="decimal" @input="nextTick(syncAsset)" />
                  <InputGroupAddon align="inline-end">€</InputGroupAddon>
                </InputGroup>
              </Field>
            </div>

            <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
              <Field v-slot="{ id }" :label="$t('financeExpense.category')" :error="errors.category_id">
                <Select :model-value="form.category_id || 'none'" @update:model-value="(value) => (form.category_id = String(value) === 'none' ? '' : String(value))">
                  <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">{{ $t('financeExpense.noCategory') }}</SelectItem>
                    <SelectItem v-for="category in categories" :key="category.id" :value="category.id">{{ category.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
              <Field v-slot="{ id }" :label="$t('financeExpense.share')" :error="errors.business_share">
                <InputGroup>
                  <InputGroupInput :id="id" v-model="form.business_share" class="tabular-nums" type="number" min="0" max="100" inputmode="numeric" />
                  <InputGroupAddon align="inline-end">%</InputGroupAddon>
                </InputGroup>
              </Field>
            </div>

            <Field v-slot="{ id }" :label="$t('financeExpense.note')" :error="errors.note">
              <Textarea :id="id" v-model="form.note" rows="3" />
            </Field>

            <Label class="flex cursor-pointer items-center gap-2 text-ui">
              <Checkbox v-model="form.is_self_issued" />
              {{ $t('financeExpense.selfIssued') }}
            </Label>
            <p v-if="errors.document_ids" class="text-small text-destructive">{{ errors.document_ids }}</p>

            <Label class="flex cursor-pointer items-center gap-3 text-ui font-medium">
              <Switch v-model="assetOn" />
              {{ $t('financeExpense.asset') }}
            </Label>
            <AssetFields
              v-if="asset"
              v-model:asset="asset"
              :errors="errors"
              :afa-categories="afaCategories"
              :inventory-no="e?.asset?.inventoryNo"
              @costs="costsTouched = true"
              @method="methodTouched = true"
            />
            <p v-if="errors.asset" class="text-small text-destructive">{{ errors.asset }}</p>

            </fieldset>

            <div class="flex flex-wrap items-center gap-2 pt-2">
              <template v-if="status !== 'ignored'">
                <Button variant="outline" type="submit" :disabled="form.processing">{{ $t('financeExpense.save') }}</Button>
                <Button v-if="status === 'draft'" variant="default" type="button" :disabled="form.processing" @click="submit(true)">{{ $t('financeExpense.book') }}</Button>
                <Button v-if="e && status === 'booked'" variant="ghost" type="button" @click="setStatus('draft')">{{ $t('financeExpense.asDraft') }}</Button>
                <Button v-if="e" variant="ghost" type="button" @click="setStatus('ignored')">{{ $t('financeExpense.discard') }}</Button>
              </template>
              <Button v-else variant="default" type="button" @click="setStatus('draft')">{{ $t('financeExpense.restore') }}</Button>
              <Button v-if="e && status !== 'booked'" class="ml-auto" variant="ghost" type="button" @click="deleteUrl = `/finanzen/ausgaben/${e.id}`">{{ $t('financeExpense.delete') }}</Button>
            </div>
          </form>
        </div>
      </div>
    </Page>
    <DraftDeleteDialog v-model:url="deleteUrl" />
  </AdminLayout>
</template>
