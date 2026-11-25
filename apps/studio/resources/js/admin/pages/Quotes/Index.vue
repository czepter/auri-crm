<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { EllipsisVertical } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import { Input } from '@/ui/input'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'
import { quoteTone } from '@/admin/lib/quoteDisplay'
import Chip from '@/ui/page/Chip.vue'
import { deleteWithUndo, isHidden } from '@/admin/lib/deferredDelete'

type Row = App.Quotes.Data.QuoteRowData

const props = defineProps<{ quotes: Row[] }>()

const { t } = useI18n()
const remove = (url: string) => deleteWithUndo(url, { deleted: t('common.deleted'), undo: t('common.undo'), failed: t('common.deleteFailed') })

const query = ref('')
const filter = ref('all')

const GROUPS: Record<string, string[]> = { drafts: ['draft'], sent: ['sent'], accepted: ['accepted'], closed: ['superseded', 'expired'] }
const filters = computed(() => ['all', ...Object.keys(GROUPS)].map((value) => ({ value, label: t(`quotesOverview.${value}`) })))

const dateOf = (quote: Row) => quote.sentAt ?? quote.createdAt ?? ''
const euros = (rows: Row[]) => formatEuros(rows.reduce((sum, row) => sum + row.total, 0))

const draftRows = computed(() => props.quotes.filter((quote) => quote.status === 'draft'))
const sentRows = computed(() => props.quotes.filter((quote) => quote.status === 'sent'))
const acceptedRows = computed(() => props.quotes.filter((quote) => quote.status === 'accepted' && (quote.acceptedAt ?? dateOf(quote)).startsWith(String(new Date().getFullYear()))))

const shown = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')

  return props.quotes
    .filter((quote) => !isHidden(`/angebote/${quote.id}?back=list`) && (filter.value === 'all' || GROUPS[filter.value].includes(quote.status))
      && (!needle || `${quote.projectName} ${quote.contact ?? ''} ${quote.number ?? ''}`.toLocaleLowerCase('de').includes(needle)))
    .sort((a, b) => Number(b.status === 'draft') - Number(a.status === 'draft') || dateOf(b).localeCompare(dateOf(a)))
})

const post = (url: string, data: Record<string, string> = {}) => router.post(url, data, { preserveScroll: true })
</script>

<template>
  <AdminLayout active="angebote">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('quotesOverview.title')">
          <Button v-if="quotes.length" :as="Link" variant="default" class="ml-auto" href="/angebote/neu">{{ $t('quotesOverview.create') }}</Button>
        </PageHead>

        <template v-if="quotes.length">
          <dl class="m-0 mx-6 mt-4.5 grid grid-cols-3 gap-px overflow-hidden rounded-lg bg-border max-[640px]:grid-cols-1">
            <div class="bg-muted p-4">
              <dt class="text-small text-ink-2">{{ $t('quotesOverview.kpiSent') }}</dt>
              <dd class="m-0 text-[22px] font-semibold tabular-nums">{{ euros(sentRows) }}</dd>
              <dd class="m-0 text-small text-ink-2">{{ $t('quotesOverview.kpiCount', sentRows.length) }}</dd>
            </div>
            <div class="bg-muted p-4">
              <dt class="text-small text-ink-2">{{ $t('quotesOverview.kpiAccepted', { year: new Date().getFullYear() }) }}</dt>
              <dd class="m-0 text-[22px] font-semibold tabular-nums">{{ euros(acceptedRows) }}</dd>
              <dd class="m-0 text-small text-ink-2">{{ $t('quotesOverview.kpiCount', acceptedRows.length) }}</dd>
            </div>
            <div class="bg-muted p-4">
              <dt class="text-small text-ink-2">{{ $t('quotesOverview.kpiDrafts') }}</dt>
              <dd class="m-0 text-[22px] font-semibold tabular-nums">{{ euros(draftRows) }}</dd>
              <dd class="m-0 text-small text-ink-2">{{ $t('quotesOverview.kpiCount', draftRows.length) }}</dd>
            </div>
          </dl>
          <div class="flex flex-wrap items-center gap-3 px-6 pt-4.5">
            <Input :aria-label="$t('quotesOverview.searchPlaceholder')" v-model="query" class="h-8 min-w-60 w-64 shrink-0" type="search" :placeholder="$t('quotesOverview.searchPlaceholder')" />
            <ChoiceGroup v-model="filter" :options="filters" />
          </div>

          <Table class="max-[640px]:block max-[640px]:overflow-x-auto">
            <thead>
              <tr>
                <TableHead>{{ $t('quotesOverview.number') }}</TableHead>
                <TableHead>{{ $t('quotesOverview.recipient') }}</TableHead>
                <TableHead class="max-[640px]:hidden">{{ $t('quotesOverview.createdAt') }}</TableHead>
                <TableHead class="max-[640px]:hidden">{{ $t('quotesOverview.validUntil') }}</TableHead>
                <TableHead align="right">{{ $t('quotesOverview.amount') }}</TableHead>
                <TableHead>{{ $t('quotesOverview.status') }}</TableHead>
                <TableHead />
              </tr>
            </thead>
            <tbody>
              <TableRow v-for="quote in shown" :key="quote.id">
                <TableCell tone="name" class="whitespace-nowrap">
                  <Link :href="`/angebote/${quote.id}`">{{ quote.number ?? '—' }}</Link>
                </TableCell>
                <TableCell>
                  {{ quote.contact }}
                  <Link class="block text-small text-ink-2" :href="`/auftraege/${quote.projectId}/angebote`">{{ quote.projectName }}</Link>
                </TableCell>
                <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ dayWithWeekday(dateOf(quote)) }}</TableCell>
                <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ quote.validUntil ? dayWithWeekday(quote.validUntil) : '' }}</TableCell>
                <TableCell align="right" class="whitespace-nowrap">{{ formatEuros(quote.total) }}</TableCell>
                <TableCell class="whitespace-nowrap"><Chip :tone="quoteTone(quote.status)">{{ quote.statusLabel }}</Chip></TableCell>
                <TableCell align="right">
                  <div class="flex items-center justify-end gap-1.5">
                    <Button v-if="quote.status === 'draft'" :as="Link" variant="outline" :href="`/angebote/${quote.id}`">{{ $t('common.edit') }}</Button>
                    <Button v-else :as="Link" variant="outline" :href="`/angebote/${quote.id}`">{{ $t('project.view') }}</Button>
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" type="button" class="hover:bg-sunk-2" :aria-label="$t('project.more')" :title="$t('project.more')">
                          <EllipsisVertical />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem v-if="quote.status === 'draft'" as-child>
                          <Link :href="`/angebote/${quote.id}/senden`">{{ $t('quotes.sendByMail') }}</Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="quote.status === 'sent'" as-child>
                          <Link :href="`/angebote/${quote.id}/nachfassen`">{{ $t('quotesOverview.followUp') }}</Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="quote.status === 'sent'" @select="post(`/angebote/${quote.id}/angenommen`)">{{ $t('quotes.markAccepted') }}</DropdownMenuItem>
                        <DropdownMenuItem v-if="quote.status === 'accepted'" @select="post(`/auftraege/${quote.projectId}/angebote`, { addendum_to: quote.id })">{{ $t('quotes.addendum') }}</DropdownMenuItem>
                        <DropdownMenuItem @select="post(`/angebote/${quote.id}/duplizieren`)">{{ $t('quotes.duplicate') }}</DropdownMenuItem>
                        <DropdownMenuItem as-child>
                          <a :href="`/angebote/${quote.id}/pdf`" target="_blank">{{ $t('invoiceDetail.download') }}</a>
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="quote.status === 'draft'" class="text-destructive" @select="remove(`/angebote/${quote.id}?back=list`)">{{ $t('common.delete') }}</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </TableCell>
              </TableRow>
            </tbody>
          </Table>
        </template>

        <Empty v-else :text="$t('quotesOverview.empty')">
          <Button :as="Link" variant="default" href="/angebote/neu">{{ $t('quotesOverview.create') }}</Button>
        </Empty>
      </div>
    </Page>
  </AdminLayout>
</template>
