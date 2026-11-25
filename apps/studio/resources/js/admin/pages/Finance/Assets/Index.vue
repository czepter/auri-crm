<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { EllipsisVertical } from 'lucide-vue-next'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Chip from '@/ui/page/Chip.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import { Button } from '@/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import DisposalDialog from '@/admin/components/finance/DisposalDialog.vue'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'

defineProps<{
  year: number
  years: number[]
  locked: boolean
  rows: App.Finance.Data.FixedAssetRowData[]
}>()

const disposing = ref<string | null>(null)
const dialog = ref(false)

function dispose(id: string) {
  disposing.value = id
  dialog.value = true
}

const clear = (id: string) => router.delete(`/finanzen/anlagen/${id}/abgang`, { preserveScroll: true })
</script>

<template>
  <AdminLayout active="anlagen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('financeAssets.title')">
          <Chip v-if="locked">{{ $t('financeAssets.locked') }}</Chip>
        </PageHead>

        <div class="max-w-[1280px]">
          <div class="flex flex-wrap items-center gap-2.5 px-6 pt-4.5 max-[640px]:px-5">
            <Select :model-value="String(year)" @update:model-value="(value) => router.get('/finanzen/anlagen', { jahr: Number(value) }, { preserveScroll: true, replace: true })">
              <SelectTrigger :aria-label="$t('financeAssets.year')" size="sm" class="w-[100px]"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="y in years" :key="y" :value="String(y)">{{ y }}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div v-if="rows.length" class="overflow-x-auto">
          <Table>
            <thead>
              <tr>
                <TableHead class="max-[640px]:hidden">{{ $t('financeAssets.inventoryNo') }}</TableHead>
                <TableHead>{{ $t('financeAssets.name') }}</TableHead>
                <TableHead class="max-[640px]:hidden">{{ $t('financeAssets.acquiredAt') }}</TableHead>
                <TableHead class="max-[640px]:hidden">{{ $t('financeAssets.method') }}</TableHead>
                <TableHead align="right" class="max-[640px]:hidden">{{ $t('financeAssets.cost') }}</TableHead>
                <TableHead align="right">{{ $t('financeAssets.bookValue', { year }) }}</TableHead>
                <TableHead />
              </tr>
            </thead>
            <tbody>
              <TableRow v-for="row in rows" :key="row.id">
                <TableCell class="whitespace-nowrap tabular-nums max-[640px]:hidden">{{ row.inventoryNo }}</TableCell>
                <TableCell tone="name">
                  <div class="max-w-[260px] max-[640px]:max-w-[130px]">
                    <span class="hidden text-small text-ink-2 max-[640px]:block">{{ row.inventoryNo }}</span>
                    <Link v-if="row.expenseId" class="block truncate" :href="`/finanzen/ausgaben/${row.expenseId}`">{{ row.name }}</Link>
                    <template v-else>{{ row.name }}</template>
                    <span v-if="row.disposedAt" class="block text-small text-ink-2">{{ $t('financeAssets.dispose') }} {{ dayWithWeekday(row.disposedAt) }} · {{ formatEuros(row.disposalProceeds ?? 0) }}</span>
                  </div>
                </TableCell>
                <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ dayWithWeekday(row.acquiredAt) }}</TableCell>
                <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ row.method === 'gwg' ? $t('financeExpense.gwg') : `${$t('financeExpense.linear')} · ${row.usefulLifeMonths} ${$t('common.unitMonths')}` }}</TableCell>
                <TableCell align="right" class="whitespace-nowrap tabular-nums max-[640px]:hidden">{{ formatEuros(row.cost) }}</TableCell>
                <TableCell align="right" class="whitespace-nowrap tabular-nums">{{ formatEuros(row.bookValue) }}</TableCell>
                <TableCell align="right">
                  <template v-if="!locked">
                    <Button v-if="!row.disposedAt" variant="outline" type="button" @click="dispose(row.id)">{{ $t('financeAssets.dispose') }}</Button>
                    <DropdownMenu v-else>
                      <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" type="button" class="hover:bg-sunk-2" :aria-label="$t('financeAssets.more')" :title="$t('financeAssets.more')"><EllipsisVertical /></Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem @select="clear(row.id)">{{ $t('financeAssets.clear') }}</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </template>
                </TableCell>
              </TableRow>
            </tbody>
          </Table>
          </div>
        </div>
      </div>
    </Page>
    <DisposalDialog v-if="disposing" :key="disposing" v-model:open="dialog" :asset-id="disposing" />
  </AdminLayout>
</template>
