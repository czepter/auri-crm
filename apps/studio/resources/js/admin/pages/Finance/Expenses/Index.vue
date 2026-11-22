<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import Chip from '@/ui/page/Chip.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'

type Row = App.Finance.Data.ExpenseRowData

const props = defineProps<{
  year: number
  years: number[]
  locked: boolean
  totals: App.Finance.Data.ExpenseTotalsData
  filters: { kategorie: string | null; status: string | null; anteil: string | null; ohneBeleg: boolean }
  categories: App.Finance.Data.ExpenseCategoryData[]
  rows: Row[]
}>()
useLiveProps(['rows', 'totals', 'locked'])

const filtered = computed(() => !!(props.filters.kategorie || props.filters.status || props.filters.anteil || props.filters.ohneBeleg))

function visit(next: Partial<{ jahr: number; kategorie: string | null; status: string | null; anteil: string | null; ohneBeleg: boolean }>) {
  const merged = {
    jahr: props.year,
    kategorie: props.filters.kategorie,
    status: props.filters.status,
    anteil: props.filters.anteil,
    ohneBeleg: props.filters.ohneBeleg,
    ...next,
  }

  router.get('/finanzen/ausgaben', {
    jahr: merged.jahr,
    ...(merged.kategorie ? { kategorie: merged.kategorie } : {}),
    ...(merged.status ? { status: merged.status } : {}),
    ...(merged.anteil ? { anteil: merged.anteil } : {}),
    ...(merged.ohneBeleg ? { 'ohne-beleg': 1 } : {}),
  }, { preserveScroll: true, preserveState: true, replace: true })
}

const all = (value: unknown) => (String(value) === 'all' ? null : String(value))

const TONE = { draft: 'warn', booked: 'success', ignored: 'neutral' } as const
</script>

<template>
  <AdminLayout active="ausgaben">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('financeExpenses.title')">
          <Chip v-if="locked">{{ $t('financeExpenses.locked') }}</Chip>
          <Button v-if="rows.length || filtered" :as="Link" class="ml-auto" variant="default" href="/finanzen/ausgaben/neu">{{ $t('financeExpenses.create') }}</Button>
        </PageHead>

        <div class="max-w-[1280px]">
          <div class="flex flex-wrap items-center gap-3 px-6 pt-4.5 max-[640px]:px-5">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-sunk px-3 py-1 text-small text-ink-2">{{ $t('financeExpenses.sum') }} <b class="font-semibold tabular-nums text-foreground">{{ formatEuros(totals.sum) }}</b></span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-sunk px-3 py-1 text-small text-ink-2">{{ $t('financeExpenses.business') }} <b class="font-semibold tabular-nums text-foreground">{{ formatEuros(totals.business) }}</b></span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-sunk px-3 py-1 text-small text-ink-2">{{ $t('financeExpenses.drafts') }} <b class="font-semibold tabular-nums text-foreground">{{ totals.drafts }}</b></span>
          </div>

          <div class="flex flex-wrap items-center gap-2.5 px-6 pt-4 max-[640px]:px-5">
            <Select :model-value="String(year)" @update:model-value="(value) => visit({ jahr: Number(value) })">
              <SelectTrigger :aria-label="$t('financeExpenses.year')" size="sm" class="w-[100px]"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="y in years" :key="y" :value="String(y)">{{ y }}</SelectItem>
              </SelectContent>
            </Select>
            <Select :model-value="filters.kategorie ?? 'all'" @update:model-value="(value) => visit({ kategorie: all(value) })">
              <SelectTrigger :aria-label="$t('financeExpenses.category')" size="sm" class="w-[190px]"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{{ $t('financeExpenses.allCategories') }}</SelectItem>
                <SelectItem v-for="category in categories" :key="category.key" :value="category.key">{{ category.label }}</SelectItem>
              </SelectContent>
            </Select>
            <Select :model-value="filters.status ?? 'all'" @update:model-value="(value) => visit({ status: all(value) })">
              <SelectTrigger :aria-label="$t('financeExpenses.status')" size="sm" class="w-[150px]"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{{ $t('financeExpenses.allStatuses') }}</SelectItem>
                <SelectItem value="draft">{{ $t('financeExpenses.draft') }}</SelectItem>
                <SelectItem value="booked">{{ $t('financeExpenses.booked') }}</SelectItem>
                <SelectItem value="ignored">{{ $t('financeExpenses.ignored') }}</SelectItem>
              </SelectContent>
            </Select>
            <Select :model-value="filters.anteil ?? 'all'" @update:model-value="(value) => visit({ anteil: all(value) })">
              <SelectTrigger :aria-label="$t('financeExpenses.share')" size="sm" class="w-[130px]"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{{ $t('financeExpenses.shareAll') }}</SelectItem>
                <SelectItem value="voll">{{ $t('financeExpenses.shareFull') }}</SelectItem>
                <SelectItem value="keiner">{{ $t('financeExpenses.shareNone') }}</SelectItem>
                <SelectItem value="anteilig">{{ $t('financeExpenses.sharePartial') }}</SelectItem>
              </SelectContent>
            </Select>
            <Label class="flex cursor-pointer items-center gap-2 text-small">
              <Checkbox :model-value="filters.ohneBeleg" @update:model-value="(value) => visit({ ohneBeleg: value === true })" />
              {{ $t('financeExpenses.withoutReceipt') }}
            </Label>
          </div>

          <Table v-if="rows.length" class="max-[640px]:block max-[640px]:overflow-x-auto">
            <thead>
              <tr>
                <TableHead>{{ $t('financeExpenses.vendor') }}</TableHead>
                <TableHead>{{ $t('financeExpenses.paidAt') }}</TableHead>
                <TableHead>{{ $t('financeExpenses.category') }}</TableHead>
                <TableHead align="right">{{ $t('financeExpenses.share') }}</TableHead>
                <TableHead align="right">{{ $t('financeExpenses.amount') }}</TableHead>
                <TableHead>{{ $t('financeExpenses.status') }}</TableHead>
              </tr>
            </thead>
            <tbody>
              <TableRow v-for="row in rows" :key="row.id">
                <TableCell tone="name">
                  <div class="w-[340px] max-w-full">
                    <Link class="block truncate" :href="`/finanzen/ausgaben/${row.id}`">{{ row.vendor ?? '—' }}</Link>
                    <span v-if="row.description" class="block truncate text-small text-ink-2">{{ row.description }}</span>
                    <span v-if="!row.hasReceipt" class="block text-small text-ink-2">{{ $t('financeExpenses.withoutReceipt') }}</span>
                  </div>
                </TableCell>
                <TableCell class="whitespace-nowrap">{{ row.paidAt ? dayWithWeekday(row.paidAt) : '' }}</TableCell>
                <TableCell>{{ row.categoryLabel }}</TableCell>
                <TableCell align="right" class="whitespace-nowrap tabular-nums">{{ row.businessShare }} %</TableCell>
                <TableCell align="right" class="whitespace-nowrap tabular-nums">
                  {{ formatEuros(row.grossEur ?? 0) }}
                  <span v-if="row.currency !== 'EUR'" class="block text-small text-ink-2">{{ (row.gross / 100).toFixed(2).replace('.', ',') }} {{ row.currency }}</span>
                </TableCell>
                <TableCell class="whitespace-nowrap"><Chip :tone="TONE[row.status]">{{ $t(`financeExpenses.${row.status}`) }}</Chip></TableCell>
              </TableRow>
            </tbody>
          </Table>
        </div>

        <Empty v-if="!rows.length && !filtered" :text="$t('financeExpenses.empty')">
          <Button :as="Link" variant="default" href="/finanzen/ausgaben/neu">{{ $t('financeExpenses.create') }}</Button>
        </Empty>
        <Empty v-else-if="!rows.length">
          <Button variant="outline" @click="router.get('/finanzen/ausgaben', { jahr: year }, { preserveScroll: true })">{{ $t('financeExpenses.reset') }}</Button>
        </Empty>
      </div>
    </Page>
  </AdminLayout>
</template>
