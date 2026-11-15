<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import { Table, TableCell, TableGroupRow, TableHead, TableRow } from '@/ui/table'
import { Button } from '@/ui/button'
import { groupContacts } from '@/admin/lib/contactQueue'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{ contacts: App.Crm.Data.ContactRowData[] }>()
useLiveProps(['contacts'])

// C-15 (#251): grouped by standing, not alphabetically. Who the studio is
// working with right now is the question this screen is opened with.
const groups = computed(() => groupContacts(props.contacts))

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

/* crm.css's own table, written out. The sheet styled td and th directly, and
   an element rule beats every utility — so the rules are gone and the values
   live here. */

/* crm.css .hrow und die Ueberschrift, die darin steht. */

/* crm.css .btn — die gefuellte Aktion. */
</script>

<template>
  <AdminLayout active="kontakte">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('contacts.title')">
          <Button :as="Link" class="ml-auto" variant="outline" href="/kontakte/import">{{ $t('contacts.import') }}</Button>
          <Button :as="Link" variant="pill" href="/kontakte/neu">{{ $t('contacts.create') }}</Button>
        </PageHead>

        <Empty v-if="!contacts.length" :text="$t('contacts.empty')">
          <Button :as="Link" variant="outline" href="/kontakte/import">{{ $t('contacts.import') }}</Button>
          <Button :as="Link" variant="pill-muted" href="/kontakte/neu">{{ $t('contacts.create') }}</Button>
        </Empty>

        <Table v-else>
          <thead>
            <tr>
              <TableHead>{{ $t('contacts.colName') }}</TableHead>
              <TableHead>{{ $t('contacts.colReach') }}</TableHead>
              <TableHead class="max-[640px]:hidden">{{ $t('contacts.colProjects') }}</TableHead>
              <TableHead align="right" class="max-[640px]:hidden">{{ $t('contacts.colNext') }}</TableHead>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in groups" :key="group.key">
              <TableGroupRow :colspan="4">{{ $t(`contacts.group.${group.key}`) }}</TableGroupRow>
              <TableRow v-for="contact in group.rows" :key="contact.id">
                <TableCell tone="name"><Link :href="`/kontakte/${contact.id}`">{{ contact.name }}</Link></TableCell>
                <TableCell tone="muted">{{ [contact.email, contact.phone].filter(Boolean).join(' · ') || '—' }}</TableCell>
                <TableCell tone="muted" class="max-[640px]:hidden">
                  {{ contact.latestProject ?? '—' }}
                  <template v-if="contact.projectCount > 1"> · {{ $t('contacts.projectCount', contact.projectCount) }}</template>
                </TableCell>
                <TableCell align="right" class="max-[640px]:hidden">{{ contact.nextDate ? day(contact.nextDate) : '—' }}</TableCell>
              </TableRow>
            </template>
          </tbody>
        </Table>
      </div>
    </Page>
  </AdminLayout>
</template>
