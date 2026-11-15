<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/crm/ProjectShell.vue'
import { useLiveProps } from '@/admin/lib/liveReload'
import { dayWithWeekday } from '@/admin/lib/dates'
import { formatEuros } from '@/lib/money'
import { Button } from '@/ui/button'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import Chip, { type ChipTone } from '@/ui/page/Chip.vue'
import Empty from '@/ui/page/Empty.vue'

defineProps<{
  header: App.Crm.Screens.CaseHeaderData
  invoices: App.Payments.Data.InvoiceRowData[]
}>()
useLiveProps(['header', 'invoices'])

const CHIP: Record<string, ChipTone> = { open: 'warn', partial: 'warn', overdue: 'danger', paid: 'success', cancelled: 'neutral' }
</script>

<template>
  <AdminLayout :active="header.isBooked ? 'projekte' : 'anfragen'">
    <ProjectShell :header="header" active="documents" child="invoices">
      <template #actions>
        <Button v-if="invoices.length" :as="Link" variant="outline" :href="`/rechnungen/neu?projekt=${header.id}`">
          {{ $t('invoicesOverview.create') }}
        </Button>
      </template>

      <Table v-if="invoices.length">
        <thead>
          <tr>
            <TableHead>{{ $t('invoicesOverview.number') }}</TableHead>
            <TableHead>{{ $t('invoicesOverview.issuedOn') }}</TableHead>
            <TableHead align="right">{{ $t('invoicesOverview.amount') }}</TableHead>
            <TableHead>{{ $t('invoicesOverview.status') }}</TableHead>
          </tr>
        </thead>
        <tbody>
          <template v-for="invoice in invoices" :key="invoice.id">
            <TableRow>
              <TableCell tone="name" class="whitespace-nowrap">
                <Link :href="`/rechnungen/${invoice.id}`">{{ invoice.number }}</Link>
              </TableCell>
              <TableCell class="whitespace-nowrap">{{ dayWithWeekday(invoice.issuedOn) }}</TableCell>
              <TableCell align="right" class="whitespace-nowrap">{{ formatEuros(invoice.amount) }}</TableCell>
              <TableCell class="whitespace-nowrap">
                <Chip :tone="CHIP[invoice.status]">{{ invoice.statusLabel }}</Chip>
              </TableCell>
            </TableRow>
            <TableRow v-for="note in invoice.creditNotes" :key="note.id">
              <TableCell class="whitespace-nowrap first:pl-10">
                <a v-if="note.hasDocument" :href="`/rechnungen/${invoice.id}/gutschrift/${note.id}`">{{ $t('payments.creditNoteRow', { number: note.number }) }}</a>
                <template v-else>{{ $t('payments.creditNoteRow', { number: note.number }) }}</template>
              </TableCell>
              <TableCell class="whitespace-nowrap">{{ dayWithWeekday(note.issuedOn) }}</TableCell>
              <TableCell align="right" class="whitespace-nowrap">{{ formatEuros(-note.amount) }}</TableCell>
              <TableCell />
            </TableRow>
          </template>
        </tbody>
      </Table>

      <Empty v-else>
        <Button :as="Link" variant="pill" :href="`/rechnungen/neu?projekt=${header.id}`">{{ $t('invoicesOverview.create') }}</Button>
      </Empty>
    </ProjectShell>
  </AdminLayout>
</template>
