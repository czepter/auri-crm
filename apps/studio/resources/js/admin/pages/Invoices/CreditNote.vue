<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import DocumentPaper from '@/admin/components/document/DocumentPaper.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'

const props = defineProps<{ invoice: App.Payments.Data.InvoiceDetailData; creditable: number; studioName: string | null }>()

const row = computed(() => props.invoice.row)
const taken = ref(props.invoice.items.map(() => true))
const amount = computed(() => props.invoice.items.reduce((sum, item, index) => sum + (taken.value[index] ? item.gross : 0), 0))
const over = computed(() => amount.value > props.creditable)
const form = useForm({ amount: 0, reason: '' })

function submit() {
  form.transform((data) => ({ ...data, amount: amount.value })).post(`/rechnungen/${row.value.id}/gutschrift`)
}
</script>

<template>
  <AdminLayout active="rechnungen">
    <FormShell :title="$t('creditNoteBuilder.title')" :kicker="$t('invoiceDetail.title', { number: row.number })" :kicker-href="`/rechnungen/${row.id}`" wide>
      <form @submit.prevent="submit">
        <DocumentPaper :studio="studioName ?? ''" :kind="$t('creditNoteBuilder.kind')" :date="dayWithWeekday(new Date().toISOString().slice(0, 10))">
          <div>
            <div class="text-small text-ink-2">{{ $t('invoiceDetail.recipient') }}</div>
            <div class="font-semibold">{{ invoice.recipient }}</div>
          </div>

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

          <div class="ml-auto grid w-[320px] max-w-full grid-cols-[1fr_auto] gap-x-6 border-t border-foreground pt-3 text-[20px] font-semibold tabular-nums max-[640px]:w-full">
            <span>{{ $t('creditNoteBuilder.kind') }}</span>
            <span class="text-right">−{{ formatEuros(amount) }}</span>
          </div>
          <p v-if="over" class="m-0 text-right text-small text-destructive">{{ $t('creditNoteBuilder.exceeds', { amount: formatEuros(creditable) }) }}</p>

          <Field :label="$t('creditNoteBuilder.reason')" :error="form.errors.reason ?? form.errors.amount">
            <Input v-model="form.reason" type="text" required />
          </Field>

          <div class="flex gap-2">
            <Button type="submit" :disabled="!amount || over || !form.reason || form.processing">{{ $t('creditNoteBuilder.submit') }}</Button>
            <Button :as="Link" variant="outline" :href="`/rechnungen/${row.id}`">{{ $t('common.cancel') }}</Button>
          </div>
        </DocumentPaper>
      </form>
    </FormShell>
  </AdminLayout>
</template>
