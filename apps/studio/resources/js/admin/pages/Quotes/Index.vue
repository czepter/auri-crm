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
import { quoteChipClass } from '@/admin/lib/quoteDisplay'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{ quotes: App.Quotes.Data.QuoteRowData[] }>()
useLiveProps(['quotes'])

const query = ref('')


const shown = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')

  return props.quotes.filter((quote) => !needle
    || `${quote.projectName} ${quote.contact ?? ''} ${quote.number ?? ''}`.toLocaleLowerCase('de').includes(needle))
})

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <AdminLayout active="angebote">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('quotesOverview.title')">
          <Button v-if="quotes.length" :as="Link" variant="pill" class="ml-auto" href="/angebote/neu">{{ $t('quotesOverview.create') }}</Button>
        </PageHead>

        <template v-if="quotes.length">
          <div class="flex px-6 pt-4.5">
            <Input :aria-label="$t('quotesOverview.searchPlaceholder')" v-model="query" class="h-8 min-w-60 w-64 shrink-0" type="search" :placeholder="$t('quotesOverview.searchPlaceholder')" />
          </div>

          <Table>
            <thead>
              <tr>
                <TableHead>{{ $t('quotesOverview.project') }}</TableHead>
                <TableHead>{{ $t('quotesOverview.number') }}</TableHead>
                <TableHead align="right">{{ $t('quotesOverview.amount') }}</TableHead>
                <TableHead>{{ $t('quotesOverview.status') }}</TableHead>
                <TableHead align="right">{{ $t('quotesOverview.createdAt') }}</TableHead>
              </tr>
            </thead>
            <tbody>
              <TableRow v-for="quote in shown" :key="quote.id">
                <TableCell tone="name">
                  <Link :href="`/angebote/${quote.id}`">{{ quote.projectName }}</Link>
                </TableCell>
                <TableCell tone="muted">{{ quote.number ?? '—' }}</TableCell>
                <TableCell align="right">{{ formatEuros(quote.total) }}</TableCell>
                <TableCell><span class="chip" :class="quoteChipClass(quote.status)">{{ quote.statusLabel }}</span></TableCell>
                <TableCell align="right" tone="muted">{{ quote.sentAt ?? quote.createdAt ? day((quote.sentAt ?? quote.createdAt)!) : '—' }}</TableCell>
              </TableRow>
            </tbody>
          </Table>
        </template>

        <Empty v-else :text="$t('quotesOverview.empty')">
          <Button :as="Link" variant="pill" href="/angebote/neu">{{ $t('quotesOverview.create') }}</Button>
        </Empty>
      </div>
    </Page>
  </AdminLayout>
</template>
