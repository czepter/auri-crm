<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { EllipsisVertical } from 'lucide-vue-next'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import { Input } from '@/ui/input'
import { Button } from '@/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'
import Chip from '@/ui/page/Chip.vue'
import { useLiveProps } from '@/admin/lib/liveReload'
import DraftDeleteDialog from '@/admin/components/DraftDeleteDialog.vue'

type Draft = { invoiceId: string; invoiceNumber: string; recipient: string | null; projectName: string | null; updatedOn: string; amount: number }

const props = defineProps<{ creditNotes: App.Payments.Data.CreditNoteRowData[]; drafts: Draft[] }>()
useLiveProps(['creditNotes', 'drafts'])
const deleteUrl = ref<string | null>(null)

const query = ref('')

const shown = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')

  return props.creditNotes.filter((note) =>
    !needle
    || `${note.number} ${note.invoiceNumber} ${note.projectName ?? ''} ${note.contact ?? ''}`
      .toLocaleLowerCase('de')
      .includes(needle))
})

const shownDrafts = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')

  return props.drafts.filter((draft) => !needle || `${draft.invoiceNumber} ${draft.projectName ?? ''} ${draft.recipient ?? ''}`.toLocaleLowerCase('de').includes(needle))
})

const total = computed(() => shown.value.reduce((sum, note) => sum + note.amount, 0))
</script>

<template>
  <AdminLayout active="gutschriften">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('creditNotesOverview.title')" />

        <div v-if="creditNotes.length || drafts.length" class="max-w-[1280px]">
          <div class="flex flex-wrap items-center gap-3 px-6 pt-4.5">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-sunk px-3 py-1 text-small text-ink-2">{{ $t('creditNotesOverview.title') }} <b class="font-semibold tabular-nums text-foreground">{{ shown.length }}</b></span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-sunk px-3 py-1 text-small text-ink-2">{{ $t('creditNotesOverview.amount') }} <b class="font-semibold tabular-nums text-foreground">−{{ formatEuros(total) }}</b></span>
            <Input :aria-label="$t('creditNotesOverview.searchPlaceholder')" v-model="query" class="h-8 min-w-60 w-64 shrink-0" type="search" :placeholder="$t('creditNotesOverview.searchPlaceholder')" />
          </div>

          <Table class="max-[640px]:block max-[640px]:overflow-x-auto">
            <thead>
              <tr>
                <TableHead>{{ $t('creditNotesOverview.number') }}</TableHead>
                <TableHead>{{ $t('creditNotesOverview.recipient') }}</TableHead>
                <TableHead>{{ $t('creditNotesOverview.issuedOn') }}</TableHead>
                <TableHead>{{ $t('creditNotesOverview.invoice') }}</TableHead>
                <TableHead>{{ $t('creditNotesOverview.reason') }}</TableHead>
                <TableHead align="right">{{ $t('creditNotesOverview.amount') }}</TableHead>
                <TableHead>{{ $t('invoicesOverview.status') }}</TableHead>
                <TableHead />
              </tr>
            </thead>
            <tbody>
              <TableRow v-for="draft in shownDrafts" :key="draft.invoiceId">
                <TableCell tone="name" class="whitespace-nowrap">—</TableCell>
                <TableCell>
                  <Link :href="`/rechnungen/${draft.invoiceId}/gutschrift/neu`">{{ draft.recipient ?? draft.projectName }}</Link>
                  <span v-if="draft.recipient && draft.projectName" class="block text-small text-ink-2">{{ draft.projectName }}</span>
                </TableCell>
                <TableCell class="whitespace-nowrap">{{ dayWithWeekday(draft.updatedOn) }}</TableCell>
                <TableCell class="whitespace-nowrap">{{ draft.invoiceNumber }}</TableCell>
                <TableCell />
                <TableCell align="right" class="whitespace-nowrap">−{{ formatEuros(draft.amount) }}</TableCell>
                <TableCell class="whitespace-nowrap"><Chip>{{ $t('creditNotesOverview.draft') }}</Chip></TableCell>
                <TableCell align="right">
                  <div class="flex items-center justify-end gap-1.5">
                    <Button :as="Link" variant="outline" :href="`/rechnungen/${draft.invoiceId}/gutschrift/neu`">{{ $t('common.edit') }}</Button>
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" type="button" class="hover:bg-sunk-2" :aria-label="$t('project.more')" :title="$t('project.more')">
                          <EllipsisVertical />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem class="text-destructive" @select="deleteUrl = `/rechnungen/${draft.invoiceId}/gutschrift/entwurf`">{{ $t('common.delete') }}</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </TableCell>
              </TableRow>
              <TableRow v-for="note in shown" :key="note.id">
                <TableCell tone="name" class="whitespace-nowrap">
                  <a v-if="note.hasDocument" :href="`/rechnungen/${note.invoiceId}/gutschrift/${note.id}`" target="_blank">{{ note.number }}</a>
                  <template v-else>{{ note.number }}</template>
                </TableCell>
                <TableCell>
                  {{ note.contact }}
                  <Link v-if="note.projectId" class="block text-small text-ink-2" :href="`/auftraege/${note.projectId}/angebote`">
                    {{ note.projectName }}
                  </Link>
                </TableCell>
                <TableCell class="whitespace-nowrap">{{ dayWithWeekday(note.issuedOn) }}</TableCell>
                <TableCell class="whitespace-nowrap">
                  <Link href="/rechnungen">{{ note.invoiceNumber }}</Link>
                </TableCell>
                <TableCell tone="muted">{{ note.reason }}</TableCell>
                <TableCell align="right" class="whitespace-nowrap">−{{ formatEuros(note.amount) }}</TableCell>
                <TableCell />
                <TableCell />
              </TableRow>
            </tbody>
          </Table>
        </div>

        <Empty v-else-if="!drafts.length" :text="$t('creditNotesOverview.empty')">
          <Button :as="Link" variant="default" href="/rechnungen">{{ $t('creditNotesOverview.toInvoices') }}</Button>
        </Empty>
      </div>
    </Page>
    <DraftDeleteDialog v-model:url="deleteUrl" />
  </AdminLayout>
</template>
