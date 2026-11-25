<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { EllipsisVertical } from 'lucide-vue-next'
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
import { deleteWithUndo, isHidden } from '@/admin/lib/deferredDelete'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday, monthDate } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'
import Chip from '@/ui/page/Chip.vue'
import { invoiceStatusDetail } from '@/admin/lib/invoiceStatus'

type Draft = { id: string; recipient: string | null; projectName: string | null; updatedOn: string; dueOn: string | null; amount: number }

type CreditNoteDraft = { invoiceId: string; updatedOn: string; amount: number }

const props = defineProps<{ invoices: App.Payments.Data.InvoiceRowData[], drafts: Draft[], creditNoteDrafts: CreditNoteDraft[], exportYears: number[] }>()
useLiveProps(['invoices', 'drafts', 'creditNoteDrafts', 'exportYears'])

const { t } = useI18n()
const remove = (url: string) => deleteWithUndo(url, { deleted: t('common.deleted'), undo: t('common.undo'), failed: t('common.deleteFailed') })

const query = ref('')

const OPEN = ['open', 'partial', 'overdue']
const matches = (value: string, status: string) => value === 'all' || (value === 'open' ? OPEN.includes(status) : status === value)
const FILTERS = ['all', 'drafts', 'open', 'paid', 'cancelled', 'credits']
const requested = new URLSearchParams(usePage().url.split('?')[1]).get('filter') ?? ''
const filter = ref(FILTERS.includes(requested) ? requested : 'all')

const filters = computed(() => FILTERS.map((value) => ({ value, label: t(`invoicesOverview.${value}`) })))

const euros = (rows: App.Payments.Data.InvoiceRowData[]) => formatEuros(rows.reduce((sum, row) => sum + row.amount - row.paidAmount, 0))
const openRows = computed(() => props.invoices.filter((invoice) => OPEN.includes(invoice.status)))
const lateRows = computed(() => props.invoices.filter((invoice) => invoice.status === 'overdue'))
const paidRows = computed(() => props.invoices.filter((invoice) => invoice.status === 'paid' && invoice.issuedOn.startsWith(String(new Date().getFullYear()))))
const paidTotal = computed(() => formatEuros(paidRows.value.reduce((sum, row) => sum + row.paidAmount, 0)))

const dialog = ref<string | null>(null)
const bind = (kind: string, id: string) => ({ open: dialog.value === `${kind}:${id}`, 'onUpdate:open': (open: boolean) => (dialog.value = open ? `${kind}:${id}` : null) })

const unpaid = (invoice: App.Payments.Data.InvoiceRowData) => invoice.status !== 'cancelled' && invoice.openInstallments.length > 0
const creditable = (invoice: App.Payments.Data.InvoiceRowData) => invoice.status !== 'cancelled' && invoice.paidAmount > invoice.credited
const quick = (invoice: App.Payments.Data.InvoiceRowData) => unpaid(invoice) ? (invoice.sentOn ? 'paid' : 'send') : creditable(invoice) ? 'credit' : null
const hasMenu = (invoice: App.Payments.Data.InvoiceRowData) => unpaid(invoice) || (invoice.status !== 'cancelled' && invoice.paidAmount === 0) || creditable(invoice) || invoice.hasDocument

const needle = computed(() => query.value.trim().toLocaleLowerCase('de'))

const shownDrafts = computed(() =>
  ['all', 'drafts'].includes(filter.value)
    ? props.drafts.filter((draft) => !isHidden(`/rechnungen/entwuerfe/${draft.id}`) && (!needle.value || `${draft.projectName ?? ''} ${draft.recipient ?? ''}`.toLocaleLowerCase('de').includes(needle.value)))
    : [])

const creditDraft = (invoice: App.Payments.Data.InvoiceRowData) =>
  isHidden(`/rechnungen/${invoice.id}/gutschrift/entwurf`) ? undefined : props.creditNoteDrafts.find((draft) => draft.invoiceId === invoice.id)

const shown = computed(() =>
  filter.value === 'drafts'
    ? []
    : props.invoices.filter((invoice) =>
      (filter.value === 'credits' ? invoice.creditNotes.length > 0 || creditDraft(invoice) !== undefined : matches(filter.value, invoice.status))
      && (!needle.value || `${invoice.number} ${invoice.projectName ?? ''} ${invoice.contact ?? ''} ${invoice.creditNotes.map((note) => note.number).join(' ')}`.toLocaleLowerCase('de').includes(needle.value))))
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
                  <div class="flex items-center justify-end gap-1.5">
                    <Button :as="Link" variant="outline" :href="`/rechnungen/entwuerfe/${draft.id}`">{{ $t('common.edit') }}</Button>
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" type="button" class="hover:bg-sunk-2" :aria-label="$t('project.more')" :title="$t('project.more')">
                          <EllipsisVertical />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem class="text-destructive" @select="remove(`/rechnungen/entwuerfe/${draft.id}`)">{{ $t('common.delete') }}</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
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
                  </TableCell>
                  <TableCell class="whitespace-nowrap">
                    <Popover>
                      <PopoverTrigger :disabled="!invoice.views.length" class="block border-0 bg-transparent p-0 text-left enabled:cursor-pointer">
                        <Chip :tone="invoice.status === 'overdue' ? 'danger' : 'neutral'">{{ invoice.statusLabel }}</Chip>
                        <span v-if="invoiceStatusDetail(invoice, t)" class="block pt-0.5 text-small text-ink-2">{{ invoiceStatusDetail(invoice, t) }}</span>
                      </PopoverTrigger>
                      <PopoverContent class="w-[420px]">
                        <p v-if="invoice.sentOn" class="m-0 pb-2 text-small text-ink-2">{{ $t('invoicesOverview.sent') }} {{ monthDate(invoice.sentOn) }}</p>
                        <DocumentViews :views="invoice.views" />
                      </PopoverContent>
                    </Popover>
                  </TableCell>
                  <TableCell align="right">
                    <div class="flex items-center justify-end gap-1.5">
                      <Button v-if="quick(invoice) === 'send'" :as="Link" variant="outline" :href="`/rechnungen/${invoice.id}/versenden`">{{ $t('invoicesOverview.send') }}</Button>
                      <Button v-else-if="quick(invoice) === 'paid'" variant="outline" type="button" @click="dialog = `paid:${invoice.id}`">{{ $t('payments.markPaid') }}</Button>
                      <Button v-else-if="quick(invoice) === 'credit'" :as="Link" variant="outline" :href="`/rechnungen/${invoice.id}/gutschrift/neu`">{{ $t('payments.creditNote') }}</Button>
                      <DropdownMenu v-if="hasMenu(invoice)">
                        <DropdownMenuTrigger as-child>
                          <Button variant="ghost" size="icon" type="button" class="hover:bg-sunk-2" :aria-label="$t('project.more')" :title="$t('project.more')">
                            <EllipsisVertical />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem v-if="unpaid(invoice) && invoice.sentOn" as-child>
                            <Link :href="`/rechnungen/${invoice.id}/versenden`">{{ $t('invoiceDetail.sendAgain') }}</Link>
                          </DropdownMenuItem>
                          <DropdownMenuItem v-if="quick(invoice) === 'send'" @select="dialog = `paid:${invoice.id}`">{{ $t('payments.markPaid') }}</DropdownMenuItem>
                          <DropdownMenuItem v-if="invoice.status !== 'cancelled' && invoice.paidAmount === 0" @select="dialog = `storno:${invoice.id}`">{{ $t('payments.storno') }}</DropdownMenuItem>
                          <DropdownMenuItem v-if="creditable(invoice) && quick(invoice) !== 'credit'" as-child>
                            <Link :href="`/rechnungen/${invoice.id}/gutschrift/neu`">{{ $t('payments.creditNote') }}</Link>
                          </DropdownMenuItem>
                          <DropdownMenuItem v-if="invoice.hasDocument" as-child>
                            <a :href="`/rechnungen/${invoice.id}/pdf`" target="_blank">{{ $t('invoiceDetail.download') }}</a>
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                    <!-- ponytail: pays the earliest open rate; an invoice with two books them one after the other. -->
                    <InstallmentPaidDialog
                      v-if="unpaid(invoice)"
                      bare
                      v-bind="bind('paid', invoice.id)"
                      :installment-id="invoice.openInstallments[0].id"
                      :subject="invoice.number"
                      :recipient="invoice.contact"
                      :remaining="invoice.openInstallments[0].remaining"
                    />
                    <InstallmentStornoDialog v-if="invoice.status !== 'cancelled' && invoice.paidAmount === 0" bare v-bind="bind('storno', invoice.id)" :invoice-id="invoice.id" :number="invoice.number" :recipient="invoice.contact" :amount="invoice.amount" />
                  </TableCell>
                </TableRow>
                <TableRow v-for="note in invoice.creditNotes" :key="note.id">
                  <TableCell class="whitespace-nowrap pl-8">
                    <a v-if="note.hasDocument" :href="`/rechnungen/${invoice.id}/gutschrift/${note.id}`" target="_blank">{{ note.number }}</a>
                    <template v-else>{{ note.number }}</template>
                  </TableCell>
                  <TableCell tone="muted">{{ note.reason }}</TableCell>
                  <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ dayWithWeekday(note.issuedOn) }}</TableCell>
                  <TableCell class="max-[640px]:hidden" />
                  <TableCell align="right" class="whitespace-nowrap">−{{ formatEuros(note.amount) }}</TableCell>
                  <TableCell class="whitespace-nowrap"><Chip>{{ $t('invoicesOverview.creditNote') }}</Chip></TableCell>
                  <TableCell />
                </TableRow>
                <TableRow v-if="creditDraft(invoice)">
                  <TableCell class="whitespace-nowrap pl-8">—</TableCell>
                  <TableCell />
                  <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ dayWithWeekday(creditDraft(invoice)!.updatedOn) }}</TableCell>
                  <TableCell class="max-[640px]:hidden" />
                  <TableCell align="right" class="whitespace-nowrap">−{{ formatEuros(creditDraft(invoice)!.amount) }}</TableCell>
                  <TableCell class="whitespace-nowrap"><Chip>{{ $t('invoicesOverview.draft') }}</Chip></TableCell>
                  <TableCell align="right">
                    <div class="flex items-center justify-end gap-1.5">
                      <Button :as="Link" variant="outline" :href="`/rechnungen/${invoice.id}/gutschrift/neu`">{{ $t('common.edit') }}</Button>
                      <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                          <Button variant="ghost" size="icon" type="button" class="hover:bg-sunk-2" :aria-label="$t('project.more')" :title="$t('project.more')">
                            <EllipsisVertical />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem class="text-destructive" @select="remove(`/rechnungen/${invoice.id}/gutschrift/entwurf`)">{{ $t('common.delete') }}</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
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
