<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import DocumentPaper from '@/admin/components/document/DocumentPaper.vue'
import InvoicePreviewPane from '@/admin/components/payments/InvoicePreviewPane.vue'
import LetterField from '@/admin/components/quote/LetterField.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { ChoiceGroup } from '@/ui/choice-group'
import { DatePicker } from '@/ui/date-picker'
import { Field } from '@/ui/field'
import { Textarea } from '@/ui/textarea'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday, isoDate } from '@/admin/lib/dates'

type Draft = { taken?: boolean[]; issued_on?: string | null; reason?: string | null; intro?: string | null; outro?: string | null; send?: boolean }

const props = defineProps<{ invoice: App.Payments.Data.InvoiceDetailData; creditable: number; draft: Draft | null; studioName: string | null }>()

const row = computed(() => props.invoice.row)
const taken = ref(props.invoice.items.map((_, index) => props.draft?.taken?.[index] ?? true))
const amount = computed(() => props.invoice.items.reduce((sum, item, index) => sum + (taken.value[index] ? item.gross : 0), 0))
const over = computed(() => amount.value > props.creditable)
const previewing = ref(false)
const form = useForm({
  issued_on: props.draft?.issued_on ?? isoDate(new Date()),
  reason: props.draft?.reason ?? '',
  intro: props.draft?.intro ?? '',
  outro: props.draft?.outro ?? '',
  send: props.draft?.send ?? true,
})
const payload = computed(() => ({ amount: amount.value, issued_on: form.issued_on || null, reason: form.reason, intro: form.intro, outro: form.outro }))

const errors = computed(() => form.errors as Record<string, string | undefined>)

function submit() {
  form.transform((data) => ({ ...data, ...payload.value })).post(`/rechnungen/${row.value.id}/gutschrift`)
}

function saveDraft() {
  form.transform((data) => ({ ...data, issued_on: data.issued_on || null, taken: taken.value })).put(`/rechnungen/${row.value.id}/gutschrift/entwurf`)
}
</script>

<template>
  <AdminLayout active="rechnungen">
    <FormShell :title="$t(props.draft ? 'creditNoteBuilder.draftTitle' : 'creditNoteBuilder.title')" :kicker="$t('invoiceDetail.title', { number: row.number })" :kicker-href="`/rechnungen/${row.id}`" wide>
      <template #head>
        <ChoiceGroup
          class="ml-auto"
          :model-value="previewing ? 'preview' : 'edit'"
          :options="[{ value: 'edit', label: $t('common.edit') }, { value: 'preview', label: $t('common.preview') }]"
          @update:model-value="(value) => (previewing = value === 'preview')"
        />
        <Button type="button" variant="outline" :disabled="form.processing" @click="saveDraft">{{ $t('invoicesCreate.saveDraft') }}</Button>
        <Button type="submit" form="credit-note" :disabled="!amount || over || !form.reason || form.processing">{{ $t('creditNoteBuilder.submit') }}</Button>
      </template>

      <InvoicePreviewPane v-if="previewing" class="mx-auto max-w-[900px]" :payload="payload" :url="`/rechnungen/${row.id}/gutschrift/vorschau`" :portal="false" />

      <DocumentPaper v-show="!previewing" :studio="studioName ?? ''" :kind="$t('creditNoteBuilder.kind')" :date="dayWithWeekday(form.issued_on || null)">
        <form id="credit-note" class="grid content-start gap-5" @submit.prevent="submit">
          <div class="grid grid-cols-[minmax(0,1fr)_260px] items-start gap-8 max-[640px]:grid-cols-1 max-[640px]:gap-5">
            <div>
              <div class="text-small text-ink-2">{{ $t('invoiceDetail.recipient') }}</div>
              <div class="font-semibold">{{ invoice.recipient }}</div>
              <div class="text-small text-ink-2">{{ $t('creditNoteBuilder.reference', { number: row.number, date: new Date(`${row.issuedOn}T12:00`).toLocaleDateString('de-DE') }) }}</div>
            </div>

            <Field v-slot="{ id }" :label="$t('creditNoteBuilder.date')" :error="form.errors.issued_on">
              <DatePicker :id="id" v-model="form.issued_on" required />
            </Field>
          </div>

          <Field :label="$t('quotes.intro')">
            <LetterField v-model="form.intro" bare />
          </Field>

          <table class="w-full border-collapse text-left">
            <thead>
              <tr class="border-b border-foreground text-small text-ink-2">
                <th class="w-10 pb-2" />
                <th class="pb-2 font-medium">{{ $t('invoiceDetail.description') }}</th>
                <th class="pb-2 text-right font-medium">{{ $t('invoiceDetail.quantity') }}</th>
                <th class="pb-2 text-right font-medium">{{ $t('creditNoteBuilder.gross') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in invoice.items" :key="index" class="border-b border-border align-top" :class="taken[index] ? '' : 'text-ink-2'">
                <td class="py-2.5"><Checkbox v-model="taken[index]" :aria-label="item.title" /></td>
                <td class="py-2.5">
                  {{ item.title }}
                  <span v-if="item.description" class="block text-small text-ink-2">{{ item.description }}</span>
                </td>
                <td class="py-2.5 text-right tabular-nums">{{ item.quantity }}</td>
                <td class="py-2.5 text-right font-medium tabular-nums">−{{ formatEuros(item.gross) }}</td>
              </tr>
            </tbody>
          </table>

          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <Field class="min-w-0 flex-1 max-[640px]:w-full" :label="$t('quotes.outro')">
              <LetterField v-model="form.outro" />
            </Field>
            <div class="grid w-[320px] max-w-full grid-cols-[1fr_auto] gap-x-6 border-t border-foreground pt-3 text-[20px] font-semibold tabular-nums max-[640px]:w-full">
              <span>{{ $t('creditNoteBuilder.kind') }}</span>
              <span class="text-right">−{{ formatEuros(amount) }}</span>
            </div>
          </div>
          <p v-if="over" class="m-0 text-right text-small text-destructive">{{ $t('creditNoteBuilder.exceeds', { amount: formatEuros(creditable) }) }}</p>

          <Field :label="$t('creditNoteBuilder.reason')" :error="errors.reason ?? errors.amount">
            <Textarea v-model="form.reason" rows="3" maxlength="1000" required />
          </Field>

          <label class="flex items-center gap-2.5">
            <Checkbox v-model="form.send" />
            {{ $t('creditNoteBuilder.send') }}
          </label>
        </form>
      </DocumentPaper>
    </FormShell>
  </AdminLayout>
</template>
