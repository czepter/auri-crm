<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Chip, { type ChipTone } from '@/ui/page/Chip.vue'
import { Button } from '@/ui/button'
import InstallmentPaidDialog from '@/admin/components/payments/InstallmentPaidDialog.vue'
import InstallmentStornoDialog from '@/admin/components/payments/InstallmentStornoDialog.vue'
import CreditNoteDialog from '@/admin/components/payments/CreditNoteDialog.vue'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{ invoice: App.Payments.Data.InvoiceDetailData }>()
useLiveProps(['invoice'])

const { t } = useI18n()

const CHIP: Record<string, ChipTone> = { open: 'warn', partial: 'warn', overdue: 'danger', paid: 'success', cancelled: 'neutral' }

const row = computed(() => props.invoice.row)
const live = computed(() => row.value.status !== 'cancelled')
const unpaid = computed(() => row.value.openInstallments.length > 0)
// Overdue is an addition to open or partial, not a status of its own.
const chipLabel = computed(() => row.value.status === 'overdue'
  ? t(row.value.paidAmount > 0 ? 'invoicesOverview.partial' : 'invoicesOverview.open')
  : row.value.statusLabel)
const sendFirst = computed(() => live.value && unpaid.value && !row.value.sentOn)
</script>

<template>
  <AdminLayout active="rechnungen">
    <Page data-tokens wide>
      <PageHead :title="$t('invoiceDetail.title', { number: row.number })" :kicker="$t('invoicesOverview.title')" kicker-href="/rechnungen">
        <Chip :tone="CHIP[row.status]">{{ chipLabel }}</Chip>
        <span v-if="row.overdueDays" class="text-small text-destructive">{{ $t('invoicesOverview.overdueDays', row.overdueDays) }}</span>
        <div class="ml-auto flex flex-wrap items-center gap-2">
          <Button v-if="row.hasDocument" as="a" variant="outline" :href="`/rechnungen/${row.id}/pdf`" target="_blank">{{ $t('invoiceDetail.download') }}</Button>
          <Button v-if="live && unpaid" :as="Link" :variant="sendFirst ? 'pill' : 'outline'" :href="`/rechnungen/${row.id}/versenden`">
            {{ $t(row.sentOn ? 'invoiceDetail.sendAgain' : 'invoicesOverview.send') }}
          </Button>
          <InstallmentPaidDialog
            v-if="live && unpaid"
            :installment-id="row.openInstallments[0].id"
            :remaining="row.openInstallments[0].remaining"
            :primary="!sendFirst"
          />
        </div>
      </PageHead>

      <div class="grid grid-cols-[minmax(0,1fr)_320px] items-start gap-8 px-6 pt-6 max-[900px]:grid-cols-1">
        <section class="rounded-lg border border-border p-8 max-[640px]:p-5">
          <div class="flex items-start justify-between gap-6">
            <div>
              <div class="text-small text-ink-2">{{ $t('invoiceDetail.recipient') }}</div>
              <div class="font-semibold">{{ invoice.recipient }}</div>
              <Link v-if="row.projectId" class="text-small text-ink-2" :href="`/auftraege/${row.projectId}`">{{ row.projectName }}</Link>
            </div>
            <div class="text-right text-small text-ink-2">
              <div class="text-[20px] font-semibold text-foreground">{{ row.number }}</div>
              <div>{{ dayWithWeekday(row.issuedOn) }}</div>
              <div v-if="row.dueOn">{{ $t('invoicesOverview.dueOn') }} {{ dayWithWeekday(row.dueOn) }}</div>
            </div>
          </div>

          <table class="mt-8 w-full border-collapse text-left">
            <thead>
              <tr class="border-b border-foreground text-small text-ink-2">
                <th class="pb-2 font-medium">{{ $t('invoiceDetail.description') }}</th>
                <th class="pb-2 text-right font-medium">{{ $t('invoiceDetail.quantity') }}</th>
                <th class="pb-2 text-right font-medium">{{ $t('invoiceDetail.unitPrice') }}</th>
                <th class="pb-2 text-right font-medium">{{ $t('invoiceDetail.net') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in invoice.items" :key="index" class="border-b border-border align-top">
                <td class="py-2.5">
                  {{ item.title }}
                  <span v-if="item.description" class="block text-small text-ink-2">{{ item.description }}</span>
                </td>
                <td class="py-2.5 text-right tabular-nums">{{ item.quantity }}</td>
                <td class="py-2.5 text-right tabular-nums">{{ formatEuros(item.unitPrice) }}</td>
                <td class="py-2.5 text-right font-medium tabular-nums">{{ formatEuros(item.total) }}</td>
              </tr>
            </tbody>
          </table>

          <dl class="mt-6 ml-auto grid w-[320px] max-w-full grid-cols-[1fr_auto] gap-x-6 gap-y-1.5 tabular-nums max-[640px]:w-full">
            <dt class="text-ink-2">{{ $t('invoiceDetail.net') }}</dt>
            <dd class="m-0 text-right text-ink-2">{{ formatEuros(invoice.net) }}</dd>
            <dt class="text-ink-2">{{ $t('invoiceDetail.vat', { rate: invoice.vatRate }) }}</dt>
            <dd class="m-0 text-right text-ink-2">{{ formatEuros(invoice.vat) }}</dd>
            <dt class="mt-2 border-t border-foreground pt-3 text-[20px] font-semibold">{{ $t('invoiceDetail.gross') }}</dt>
            <dd class="m-0 mt-2 border-t border-foreground pt-3 text-right text-[20px] font-semibold">{{ formatEuros(invoice.gross) }}</dd>
          </dl>
        </section>

        <aside class="flex flex-col gap-6">
          <section v-if="invoice.quoteId">
            <h2 class="m-0 mb-1.5 text-small font-semibold text-ink-2">{{ $t('invoiceDetail.quote') }}</h2>
            <Link class="font-medium" :href="`/angebote/${invoice.quoteId}`">{{ invoice.quoteNumber ?? $t('invoiceDetail.quoteOpen') }}</Link>
          </section>

          <section>
            <h2 class="m-0 mb-2 text-small font-semibold text-ink-2">{{ $t('invoiceDetail.history') }}</h2>
            <ol class="m-0 flex list-none flex-col gap-4 p-0">
              <li v-for="(event, index) in invoice.events" :key="index">
                <div class="font-medium">
                  {{ $t(`invoiceDetail.event.${event.kind}`) }}
                  <span v-if="event.amount" class="tabular-nums">{{ formatEuros(event.amount) }}</span>
                </div>
                <div class="text-small text-ink-2">{{ dayWithWeekday(event.on) }}</div>
              </li>
            </ol>
          </section>

          <section v-if="live && (row.paidAmount === 0 || row.paidAmount > row.credited)" class="flex flex-wrap gap-2">
            <InstallmentStornoDialog v-if="row.paidAmount === 0" :invoice-id="row.id" />
            <CreditNoteDialog v-if="row.paidAmount > row.credited" :invoice-id="row.id" :amount="row.paidAmount - row.credited" />
          </section>
        </aside>
      </div>
    </Page>
  </AdminLayout>
</template>
