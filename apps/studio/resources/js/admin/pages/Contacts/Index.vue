<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

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
</script>

<template>
  <AdminLayout active="kontakte">
    <div data-theme="crm">
      <div class="hrow">
        <h1>{{ $t('contacts.title') }}</h1>
      </div>

      <table v-if="contacts.length" style="margin-top: var(--s5)">
        <thead>
          <tr>
            <th>{{ $t('contacts.colName') }}</th>
            <th>{{ $t('contacts.colReach') }}</th>
            <th>{{ $t('contacts.colProjects') }}</th>
            <th class="r">{{ $t('contacts.colNext') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in groups" :key="group.key">
            <tr><td class="grp" colspan="4">{{ $t(`contacts.group.${group.key}`) }}</td></tr>
            <tr v-for="contact in group.rows" :key="contact.id">
              <td class="nm"><Link :href="`/kontakte/${contact.id}`">{{ contact.name }}</Link></td>
              <td class="mu">{{ [contact.email, contact.phone].filter(Boolean).join(' · ') || '—' }}</td>
              <td class="mu">
                {{ contact.latestProject ?? '—' }}
                <template v-if="contact.projectCount > 1"> · {{ $t('contacts.projectCount', contact.projectCount) }}</template>
              </td>
              <td class="r">{{ contact.nextDate ? day(contact.nextDate) : '—' }}</td>
            </tr>
          </template>
        </tbody>
      </table>

      <Link v-else class="btn" href="/anfragen/neu" style="margin-top: var(--s5)">
        {{ $t('leads.createKicker') }}
      </Link>
    </div>
  </AdminLayout>
</template>
