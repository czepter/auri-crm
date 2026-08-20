<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Table, TableCell, TableGroupRow, TableHead, TableRow } from '@/ui/table'
import { Button } from '@/ui/button'

const props = defineProps<{ contacts: App.Crm.Data.ContactRowData[] }>()

// C-15 (#251): grouped by standing, not alphabetically. Who the studio is
// working with right now is the question this screen is opened with.
const GROUPS = ['running', 'past', 'none'] as const

const groups = computed(() =>
  GROUPS.map((key) => ({ key, rows: props.contacts.filter((contact) => contact.standing === key) }))
    .filter((group) => group.rows.length),
)

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

/* crm.css's own table, written out. The sheet styled td and th directly, and
   an element rule beats every utility — so the rules are gone and the values
   live here. */

/* crm.css .hrow und die Ueberschrift, die darin steht. */

/* crm.css .btn — die gefuellte Aktion. */
</script>

<template>
  <AdminLayout active="kontakte">
    <div data-tokens="crm">
      <PageHead :title="$t('contacts.title')" />

      <Table v-if="contacts.length">
        <thead>
          <tr>
            <TableHead>{{ $t('contacts.colName') }}</TableHead>
            <TableHead>{{ $t('contacts.colReach') }}</TableHead>
            <TableHead>{{ $t('contacts.colProjects') }}</TableHead>
            <TableHead align="right">{{ $t('contacts.colNext') }}</TableHead>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in groups" :key="group.key">
            <TableGroupRow :colspan="4">{{ $t(`contacts.group.${group.key}`) }}</TableGroupRow>
            <TableRow v-for="contact in group.rows" :key="contact.id">
              <TableCell tone="name"><Link :href="`/kontakte/${contact.id}`">{{ contact.name }}</Link></TableCell>
              <TableCell tone="muted">{{ [contact.email, contact.phone].filter(Boolean).join(' · ') || '—' }}</TableCell>
              <TableCell tone="muted">
                {{ contact.latestProject ?? '—' }}
                <template v-if="contact.projectCount > 1"> · {{ $t('contacts.projectCount', contact.projectCount) }}</template>
              </TableCell>
              <TableCell align="right">{{ contact.nextDate ? day(contact.nextDate) : '—' }}</TableCell>
            </TableRow>
          </template>
        </tbody>
      </Table>

      <Button v-else :as="Link" variant="pill" class="mt-5" href="/anfragen/neu">
        {{ $t('leads.createKicker') }}
      </Button>
    </div>
  </AdminLayout>
</template>
