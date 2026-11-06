<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import { Input } from '@/ui/input'
import { Button } from '@/ui/button'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{ creditNotes: App.Payments.Data.CreditNoteRowData[] }>()
useLiveProps(['creditNotes'])

const query = ref('')

const shown = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')

  return props.creditNotes.filter((note) =>
    !needle
    || `${note.number} ${note.invoiceNumber} ${note.projectName ?? ''} ${note.contact ?? ''}`
      .toLocaleLowerCase('de')
      .includes(needle))
})
</script>

<template>
  <AdminLayout active="gutschriften">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('creditNotesOverview.title')" />

        <template v-if="creditNotes.length">
          <div class="flex flex-wrap items-center gap-3 px-6 pt-4.5">
            <Input v-model="query" class="h-8 w-64 shrink-0" type="search" :placeholder="$t('creditNotesOverview.searchPlaceholder')" />
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
              </tr>
            </thead>
            <tbody>
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
              </TableRow>
            </tbody>
          </Table>
        </template>

        <Empty v-else :text="$t('creditNotesOverview.empty')">
          <Button :as="Link" variant="pill" href="/rechnungen">{{ $t('creditNotesOverview.toInvoices') }}</Button>
        </Empty>
      </div>
    </Page>
  </AdminLayout>
</template>
