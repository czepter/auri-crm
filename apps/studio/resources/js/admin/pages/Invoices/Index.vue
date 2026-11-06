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
import { Popover, PopoverContent, PopoverTrigger } from '@/ui/popover'
import DocumentViews from '@/admin/components/DocumentViews.vue'
import { ChoiceGroup } from '@/ui/choice-group'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import InstallmentPaidDialog from '@/admin/components/payments/InstallmentPaidDialog.vue'
import InstallmentStornoDialog from '@/admin/components/payments/InstallmentStornoDialog.vue'
import CreditNoteDialog from '@/admin/components/payments/CreditNoteDialog.vue'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{ invoices: App.Payments.Data.InvoiceRowData[], exportYears: number[] }>()
useLiveProps(['invoices', 'exportYears'])

const { t } = useI18n()

const query = ref('')

const OPEN = ['open', 'partial', 'overdue']
const matches = (value: string, status: string) => value === 'all' || (value === 'open' ? OPEN.includes(status) : status === value)
const filter = ref(['open', 'paid', 'cancelled'].find((value) => props.invoices.some((invoice) => matches(value, invoice.status))) ?? 'all')

const filters = computed(() => ['open', 'paid', 'cancelled', 'all'].map((value) => ({ value, label: t(`invoicesOverview.${value}`) })))

const CHIP: Record<string, string> = { open: 'now', partial: 'now', overdue: 'fail', paid: 'done', cancelled: 'off' }

const shown = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')

  return props.invoices.filter((invoice) =>
    matches(filter.value, invoice.status)
    && (!needle || `${invoice.number} ${invoice.projectName ?? ''} ${invoice.contact ?? ''}`.toLocaleLowerCase('de').includes(needle)))
})
</script>

<template>
  <AdminLayout active="rechnungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('invoicesOverview.title')">
          <div class="ml-auto flex items-center gap-2">
            <DropdownMenu v-if="exportYears.length">
              <DropdownMenuTrigger as-child>
                <Button variant="outline" type="button">{{ $t('invoicesOverview.export') }}</Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem v-for="year in exportYears" :key="year" as-child>
                  <a :href="`/einstellungen/rechnungen/export?jahr=${year}`">{{ $t('invoicesOverview.exportYear', { year }) }}</a>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <Button v-if="invoices.length" :as="Link" variant="pill" href="/rechnungen/neu">{{ $t('invoicesOverview.create') }}</Button>
          </div>
        </PageHead>

        <template v-if="invoices.length">
          <div class="flex flex-wrap items-center gap-3 px-6 pt-4.5">
            <Input v-model="query" class="h-8 w-64 shrink-0" type="search" :placeholder="$t('invoicesOverview.searchPlaceholder')" />
            <ChoiceGroup v-model="filter" variant="segmented" :options="filters" />
          </div>

          <Table class="max-[640px]:block max-[640px]:overflow-x-auto">
            <thead>
              <tr>
                <TableHead>{{ $t('invoicesOverview.number') }}</TableHead>
                <TableHead>{{ $t('invoicesOverview.recipient') }}</TableHead>
                <TableHead class="max-[640px]:hidden">{{ $t('invoicesOverview.issuedOn') }}</TableHead>
                <TableHead class="max-[640px]:hidden">{{ $t('invoicesOverview.dueOn') }}</TableHead>
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
                    <Link v-if="invoice.projectId" class="block text-small text-ink-2" :href="`/auftraege/${invoice.projectId}/angebote`">
                      {{ invoice.projectName }}
                    </Link>
                  </TableCell>
                  <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ dayWithWeekday(invoice.issuedOn) }}</TableCell>
                  <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ invoice.dueOn ? dayWithWeekday(invoice.dueOn) : '' }}</TableCell>
                  <TableCell align="right" class="whitespace-nowrap">
                    {{ formatEuros(invoice.amount) }}
                    <span v-if="invoice.status === 'partial' || (invoice.status === 'overdue' && invoice.paidAmount)" class="block text-small text-ink-2">
                      {{ $t('payments.open', { amount: formatEuros(invoice.amount - invoice.paidAmount) }) }}
                    </span>
                  </TableCell>
                  <TableCell class="whitespace-nowrap">
                    <span class="chip" :class="CHIP[invoice.status]">{{ invoice.statusLabel }}</span>
                    <Popover v-if="invoice.views.length">
                      <PopoverTrigger class="ml-2 cursor-pointer border-0 bg-transparent p-0 text-small text-ink-2 underline decoration-dotted">
                        {{ $t(invoice.viewedOn ? 'invoicesOverview.viewed' : 'documentViews.title') }}
                      </PopoverTrigger>
                      <PopoverContent class="w-[420px]">
                        <DocumentViews :views="invoice.views" />
                      </PopoverContent>
                    </Popover>
                    <span v-else-if="invoice.sentOn" class="ml-2 text-small text-ink-2">
                      {{ $t('invoicesOverview.sent') }}
                    </span>
                  </TableCell>
                  <TableCell align="right">
                    <div v-if="invoice.status !== 'cancelled'" class="flex flex-wrap justify-end gap-1.5">
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

        <Empty v-else class="mx-8 mt-7">
          <Button :as="Link" variant="pill" href="/rechnungen/neu">{{ $t('invoicesOverview.create') }}</Button>
        </Empty>
      </div>
    </Page>
  </AdminLayout>
</template>
