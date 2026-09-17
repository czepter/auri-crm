<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import { Input } from '@/ui/input'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import InstallmentPaidDialog from '@/admin/components/InstallmentPaidDialog.vue'
import InstallmentStornoDialog from '@/admin/components/InstallmentStornoDialog.vue'
import CreditNoteDialog from '@/admin/components/CreditNoteDialog.vue'
import { formatEuros } from '@/admin/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'

const props = defineProps<{ invoices: App.Payments.Data.InvoiceRowData[] }>()

const { t } = useI18n()

const query = ref('')
const filter = ref('open')

const filters = computed(() => ['open', 'paid', 'cancelled', 'all'].map((value) => ({ value, label: t(`invoicesOverview.${value}`) })))

const OPEN = ['open', 'partial', 'overdue']
const CHIP: Record<string, string> = { open: 'now', partial: 'now', overdue: 'fail', paid: 'done', cancelled: 'off' }

const shown = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')

  return props.invoices.filter((invoice) =>
    (filter.value === 'all' || (filter.value === 'open' ? OPEN.includes(invoice.status) : invoice.status === filter.value))
    && (!needle || `${invoice.number} ${invoice.projectName ?? ''} ${invoice.contact ?? ''}`.toLocaleLowerCase('de').includes(needle)))
})
</script>

<template>
  <AdminLayout active="rechnungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('invoicesOverview.title')">
          <Button :as="Link" variant="pill" class="ml-auto" href="/rechnungen/neu">{{ $t('invoicesOverview.create') }}</Button>
        </PageHead>

        <template v-if="invoices.length">
          <div class="flex flex-wrap items-center gap-3 px-8 pt-4.5">
            <Input v-model="query" class="h-8 w-64" type="search" :placeholder="$t('invoicesOverview.searchPlaceholder')" />
            <ChoiceGroup v-model="filter" variant="segmented" :options="filters" />
          </div>

          <Table>
            <thead>
              <tr>
                <TableHead>{{ $t('invoicesOverview.number') }}</TableHead>
                <TableHead>{{ $t('invoicesOverview.recipient') }}</TableHead>
                <TableHead>{{ $t('invoicesOverview.issuedOn') }}</TableHead>
                <TableHead>{{ $t('invoicesOverview.dueOn') }}</TableHead>
                <TableHead align="right">{{ $t('invoicesOverview.amount') }}</TableHead>
                <TableHead>{{ $t('invoicesOverview.status') }}</TableHead>
                <TableHead />
              </tr>
            </thead>
            <tbody>
              <template v-for="invoice in shown" :key="invoice.id">
                <TableRow>
                  <TableCell tone="name" class="whitespace-nowrap">
                    <a v-if="invoice.hasDocument" :href="`/rechnungen/${invoice.id}/pdf`" target="_blank">{{ invoice.number }}</a>
                    <template v-else>{{ invoice.number }}</template>
                  </TableCell>
                  <TableCell>
                    {{ invoice.contact }}
                    <Link v-if="invoice.projectId" class="block text-[11.5px] text-ink-2" :href="`/auftraege/${invoice.projectId}/angebote`">
                      {{ invoice.projectName }}
                    </Link>
                  </TableCell>
                  <TableCell class="whitespace-nowrap">{{ dayWithWeekday(invoice.issuedOn) }}</TableCell>
                  <TableCell class="whitespace-nowrap">{{ invoice.dueOn ? dayWithWeekday(invoice.dueOn) : '' }}</TableCell>
                  <TableCell align="right" class="whitespace-nowrap">
                    {{ formatEuros(invoice.amount) }}
                    <span v-if="invoice.status === 'partial' || (invoice.status === 'overdue' && invoice.paidAmount)" class="block text-[11.5px] text-ink-2">
                      {{ $t('payments.open', { amount: formatEuros(invoice.amount - invoice.paidAmount) }) }}
                    </span>
                  </TableCell>
                  <TableCell class="whitespace-nowrap">
                    <span class="chip" :class="CHIP[invoice.status]">{{ invoice.statusLabel }}</span>
                    <span v-if="invoice.viewedOn || invoice.sentOn" class="ml-2 text-[11.5px] text-ink-2">
                      {{ $t(invoice.viewedOn ? 'invoicesOverview.viewed' : 'invoicesOverview.sent') }}
                    </span>
                  </TableCell>
                  <TableCell align="right">
                    <div v-if="invoice.status !== 'cancelled'" class="flex justify-end gap-1.5">
                      <Button
                        v-if="OPEN.includes(invoice.status)"
                        :as="Link"
                        variant="outline"
                        :href="`/rechnungen/${invoice.id}/versenden`"
                      >{{ $t('invoicesOverview.send') }}</Button>
                      <!-- ponytail: pays the earliest open rate; an invoice with two books them one after the other. -->
                      <InstallmentPaidDialog
                        v-if="invoice.openInstallments.length"
                        :installment-id="invoice.openInstallments[0].id"
                        :remaining="invoice.openInstallments[0].remaining"
                      />
                      <InstallmentStornoDialog v-if="invoice.paidAmount === 0" :invoice-id="invoice.id" />
                      <CreditNoteDialog
                        v-if="invoice.paidAmount > invoice.credited"
                        :invoice-id="invoice.id"
                        :amount="invoice.paidAmount - invoice.credited"
                      />
                    </div>
                  </TableCell>
                </TableRow>

                <TableRow v-for="note in invoice.creditNotes" :key="note.id">
                  <TableCell tone="muted" class="whitespace-nowrap">
                    <a v-if="note.hasDocument" :href="`/rechnungen/${invoice.id}/gutschrift/${note.id}`" target="_blank">{{ note.number }}</a>
                    <template v-else>{{ note.number }}</template>
                  </TableCell>
                  <TableCell tone="muted">{{ $t('payments.creditNote') }}</TableCell>
                  <TableCell tone="muted" class="whitespace-nowrap">{{ dayWithWeekday(note.issuedOn) }}</TableCell>
                  <TableCell />
                  <TableCell align="right" tone="muted" class="whitespace-nowrap">−{{ formatEuros(note.amount) }}</TableCell>
                  <TableCell colspan="2" />
                </TableRow>
              </template>
            </tbody>
          </Table>
        </template>

        <Empty v-else class="mx-8 mt-7" :text="$t('invoicesOverview.empty')">
          <Button :as="Link" variant="pill" href="/rechnungen/neu">{{ $t('invoicesOverview.create') }}</Button>
        </Empty>
      </div>
    </Page>
  </AdminLayout>
</template>
