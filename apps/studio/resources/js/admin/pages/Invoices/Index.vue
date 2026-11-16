<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
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
import Chip, { type ChipTone } from '@/ui/page/Chip.vue'

type Draft = { id: string; recipient: string | null; projectName: string | null; updatedOn: string; dueOn: string | null; amount: number }

const props = defineProps<{ invoices: App.Payments.Data.InvoiceRowData[], drafts: Draft[], exportYears: number[] }>()
useLiveProps(['invoices', 'drafts', 'exportYears'])

const { t } = useI18n()

const query = ref('')

const OPEN = ['open', 'partial', 'overdue']
const matches = (value: string, status: string) => value === 'all' || (value === 'open' ? OPEN.includes(status) : status === value)
const filter = ref('all')

const filters = computed(() => ['all', 'drafts', 'open', 'paid', 'cancelled'].map((value) => ({ value, label: t(`invoicesOverview.${value}`) })))

const CHIP: Record<string, ChipTone> = { open: 'warn', partial: 'warn', overdue: 'danger', paid: 'success', cancelled: 'neutral' }

const euros = (rows: App.Payments.Data.InvoiceRowData[]) => formatEuros(rows.reduce((sum, row) => sum + row.amount - row.paidAmount, 0))
const openRows = computed(() => props.invoices.filter((invoice) => OPEN.includes(invoice.status)))
const lateRows = computed(() => props.invoices.filter((invoice) => invoice.status === 'overdue'))
const paidRows = computed(() => props.invoices.filter((invoice) => invoice.status === 'paid' && invoice.issuedOn.startsWith(String(new Date().getFullYear()))))
const paidTotal = computed(() => formatEuros(paidRows.value.reduce((sum, row) => sum + row.paidAmount, 0)))

const needle = computed(() => query.value.trim().toLocaleLowerCase('de'))

const shownDrafts = computed(() =>
  ['all', 'drafts'].includes(filter.value)
    ? props.drafts.filter((draft) => !needle.value || `${draft.projectName ?? ''} ${draft.recipient ?? ''}`.toLocaleLowerCase('de').includes(needle.value))
    : [])

const shown = computed(() =>
  filter.value === 'drafts'
    ? []
    : props.invoices.filter((invoice) =>
      matches(filter.value, invoice.status)
      && (!needle.value || `${invoice.number} ${invoice.projectName ?? ''} ${invoice.contact ?? ''}`.toLocaleLowerCase('de').includes(needle.value))))
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
            <Button v-if="invoices.length || drafts.length" :as="Link" variant="default" href="/rechnungen/neu">{{ $t('invoicesOverview.create') }}</Button>
          </div>
        </PageHead>

        <template v-if="invoices.length || drafts.length">
          <dl class="m-0 mx-6 mt-4.5 grid grid-cols-3 gap-px overflow-hidden rounded-lg bg-border max-[640px]:grid-cols-1">
            <div class="bg-muted p-4">
              <dt class="text-small text-ink-2">{{ $t('invoicesOverview.kpiOpen') }}</dt>
              <dd class="m-0 text-[22px] font-semibold tabular-nums">{{ euros(openRows) }}</dd>
              <dd class="m-0 text-small text-ink-2">{{ $t('invoicesOverview.kpiCount', openRows.length) }}</dd>
            </div>
            <div class="bg-muted p-4">
              <dt class="text-small text-ink-2">{{ $t('invoicesOverview.kpiOverdue') }}</dt>
              <dd class="m-0 text-[22px] font-semibold tabular-nums">{{ euros(lateRows) }}</dd>
              <dd class="m-0 text-small text-ink-2">{{ $t('invoicesOverview.kpiCount', lateRows.length) }}</dd>
            </div>
            <div class="bg-muted p-4">
              <dt class="text-small text-ink-2">{{ $t('invoicesOverview.kpiPaid', { year: new Date().getFullYear() }) }}</dt>
              <dd class="m-0 text-[22px] font-semibold tabular-nums">{{ paidTotal }}</dd>
              <dd class="m-0 text-small text-ink-2">{{ $t('invoicesOverview.kpiCount', paidRows.length) }}</dd>
            </div>
          </dl>
          <div class="flex flex-wrap items-center gap-3 px-6 pt-4.5">
            <Input :aria-label="$t('invoicesOverview.searchPlaceholder')" v-model="query" class="h-8 min-w-60 w-64 shrink-0" type="search" :placeholder="$t('invoicesOverview.searchPlaceholder')" />
            <ChoiceGroup v-model="filter" :options="filters" />
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
              <TableRow v-for="draft in shownDrafts" :key="draft.id">
                <TableCell tone="name" class="whitespace-nowrap">—</TableCell>
                <TableCell>
                  <Link :href="`/rechnungen/entwuerfe/${draft.id}`">{{ draft.recipient ?? draft.projectName }}</Link>
                  <span v-if="draft.recipient && draft.projectName" class="block text-small text-ink-2">{{ draft.projectName }}</span>
                </TableCell>
                <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ dayWithWeekday(draft.updatedOn) }}</TableCell>
                <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ draft.dueOn ? dayWithWeekday(draft.dueOn) : '' }}</TableCell>
                <TableCell align="right" class="whitespace-nowrap">{{ formatEuros(draft.amount) }}</TableCell>
                <TableCell class="whitespace-nowrap"><Chip>{{ $t('invoicesOverview.draft') }}</Chip></TableCell>
                <TableCell align="right">
                  <div class="flex flex-wrap justify-end gap-1.5">
                    <Button :as="Link" variant="outline" :href="`/rechnungen/entwuerfe/${draft.id}`">{{ $t('common.edit') }}</Button>
                    <Button variant="outline" type="button" @click="router.delete(`/rechnungen/entwuerfe/${draft.id}`, { preserveScroll: true })">{{ $t('common.delete') }}</Button>
                  </div>
                </TableCell>
              </TableRow>
              <template v-for="invoice in shown" :key="invoice.id">
                <TableRow>
                  <TableCell tone="name" class="whitespace-nowrap">
                    <Link :href="`/rechnungen/${invoice.id}`">{{ invoice.number }}</Link>
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
                    <Chip :tone="CHIP[invoice.status]">{{ invoice.status === 'overdue' ? $t(invoice.paidAmount ? 'invoicesOverview.partial' : 'invoicesOverview.open') : invoice.statusLabel }}</Chip>
                    <span v-if="invoice.overdueDays" class="ml-2 text-small text-destructive">{{ $t('invoicesOverview.overdueDays', invoice.overdueDays) }}</span>
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
              </template>
            </tbody>
          </Table>
        </template>

        <Empty v-else-if="!drafts.length" :text="$t('invoicesOverview.empty')">
          <Button :as="Link" variant="default" href="/rechnungen/neu">{{ $t('invoicesOverview.create') }}</Button>
        </Empty>
      </div>
    </Page>
  </AdminLayout>
</template>
