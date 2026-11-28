<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
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
import Chip, { type ChipTone } from '@/ui/page/Chip.vue'
import { dayWithWeekday } from '@/admin/lib/dates'

type Row = App.Contracts.Data.ContractRowData

const props = defineProps<{ contracts: Row[] }>()

const { t } = useI18n()

const query = ref('')
const filter = ref('all')

const statusOf = (contract: Row) => contract.state === 'draft' ? 'drafts' : contract.state === 'superseded' ? 'superseded' : contract.signedOn ? 'signed' : 'sent'
const CHIP: Record<string, ChipTone> = { drafts: 'neutral', sent: 'warn', signed: 'success', superseded: 'neutral' }
const filters = computed(() => ['all', 'drafts', 'sent', 'signed', 'superseded'].map((value) => ({ value, label: t(`contractsOverview.${value}`) })))

const dateOf = (contract: Row) => contract.sentOn ?? contract.createdOn

const year = String(new Date().getFullYear())
const count = (rows: Row[]) => t('contractsOverview.kpiCount', rows.length)
const sentRows = computed(() => props.contracts.filter((contract) => statusOf(contract) === 'sent'))
const signedRows = computed(() => props.contracts.filter((contract) => contract.signedOn?.startsWith(year)))
const draftRows = computed(() => props.contracts.filter((contract) => statusOf(contract) === 'drafts'))

const shown = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')

  return props.contracts
    .filter((contract) => (filter.value === 'all' || statusOf(contract) === filter.value)
      && (!needle || `${contract.projectName} ${contract.contact ?? ''}`.toLocaleLowerCase('de').includes(needle)))
    .sort((a, b) => Number(b.state === 'draft') - Number(a.state === 'draft') || dateOf(b).localeCompare(dateOf(a)))
})
</script>

<template>
  <AdminLayout active="vertraege">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('contractsOverview.title')" />

        <template v-if="contracts.length">
          <dl class="m-0 mx-6 mt-4.5 grid grid-cols-3 gap-px overflow-hidden rounded-lg bg-border max-[640px]:grid-cols-1">
            <div class="bg-muted p-4">
              <dt class="text-small text-ink-2">{{ $t('contractsOverview.kpiSent') }}</dt>
              <dd class="m-0 text-[22px] font-semibold tabular-nums">{{ sentRows.length }}</dd>
              <dd class="m-0 text-small text-ink-2">{{ count(sentRows) }}</dd>
            </div>
            <div class="bg-muted p-4">
              <dt class="text-small text-ink-2">{{ $t('contractsOverview.kpiSigned', { year }) }}</dt>
              <dd class="m-0 text-[22px] font-semibold tabular-nums">{{ signedRows.length }}</dd>
              <dd class="m-0 text-small text-ink-2">{{ count(signedRows) }}</dd>
            </div>
            <div class="bg-muted p-4">
              <dt class="text-small text-ink-2">{{ $t('contractsOverview.kpiDrafts') }}</dt>
              <dd class="m-0 text-[22px] font-semibold tabular-nums">{{ draftRows.length }}</dd>
              <dd class="m-0 text-small text-ink-2">{{ count(draftRows) }}</dd>
            </div>
          </dl>
          <div class="flex flex-wrap items-center gap-3 px-6 pt-4.5">
            <Input :aria-label="$t('contractsOverview.searchPlaceholder')" v-model="query" class="h-8 min-w-60 w-64 shrink-0" type="search" :placeholder="$t('contractsOverview.searchPlaceholder')" />
            <ChoiceGroup v-model="filter" :options="filters" />
          </div>

          <Table class="max-[640px]:block max-[640px]:overflow-x-auto">
            <thead>
              <tr>
                <TableHead>{{ $t('contractsOverview.version') }}</TableHead>
                <TableHead>{{ $t('contractsOverview.recipient') }}</TableHead>
                <TableHead class="max-[640px]:hidden">{{ $t('contractsOverview.date') }}</TableHead>
                <TableHead class="max-[640px]:hidden">{{ $t('contractsOverview.signedOn') }}</TableHead>
                <TableHead class="max-[640px]:hidden">{{ $t('contractsOverview.deadline') }}</TableHead>
                <TableHead>{{ $t('contractsOverview.status') }}</TableHead>
                <TableHead />
              </tr>
            </thead>
            <tbody>
              <TableRow v-for="contract in shown" :key="contract.id">
                <TableCell tone="name" class="whitespace-nowrap">
                  <Link :href="`/auftraege/${contract.projectId}/vertrag`">{{ $t('project.contractVersionLabel', { version: contract.version }) }}</Link>
                </TableCell>
                <TableCell>
                  {{ contract.contact }}
                  <Link class="block text-small text-ink-2" :href="`/auftraege/${contract.projectId}/vertrag`">{{ contract.projectName }}</Link>
                </TableCell>
                <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ dayWithWeekday(dateOf(contract)) }}</TableCell>
                <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ contract.signedOn ? dayWithWeekday(contract.signedOn) : '' }}</TableCell>
                <TableCell class="whitespace-nowrap max-[640px]:hidden">{{ contract.deadlineOn ? dayWithWeekday(contract.deadlineOn) : '' }}</TableCell>
                <TableCell class="whitespace-nowrap"><Chip :tone="CHIP[statusOf(contract)]">{{ statusOf(contract) === 'drafts' ? $t('invoicesOverview.draft') : $t(`contractsOverview.${statusOf(contract)}`) }}</Chip></TableCell>
                <TableCell align="right">
                  <div class="flex items-center justify-end gap-1.5">
                    <Button :as="Link" variant="outline" :href="`/auftraege/${contract.projectId}/vertrag`">{{ $t(contract.state === 'draft' ? 'common.edit' : 'project.view') }}</Button>
                    <DropdownMenu v-if="contract.hasDocument">
                      <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" type="button" class="hover:bg-sunk-2" :aria-label="$t('project.more')" :title="$t('project.more')">
                          <EllipsisVertical />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem as-child>
                          <a :href="`/vertraege/${contract.id}/pdf`" target="_blank">{{ $t('invoiceDetail.download') }}</a>
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </TableCell>
              </TableRow>
            </tbody>
          </Table>
        </template>

        <Empty v-else :text="$t('contractsOverview.empty')">
          <Button :as="Link" variant="default" href="/projekte">{{ $t('contractsOverview.toProjects') }}</Button>
        </Empty>
      </div>
    </Page>
  </AdminLayout>
</template>
