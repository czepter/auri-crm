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

/* crm.css's own table, written out. The sheet styled td and th directly, and
   an element rule beats every utility — so the rules are gone and the values
   live here. */
const TH = 'px-8 pt-0 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2'
const TH_R = 'px-8 pt-0 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2 text-right'
const TD = 'px-8 py-2.5 align-baseline text-[13px] leading-[1.47] shadow-[inset_0_1px_0_#F0F0F2]'
const TD_NM = 'px-8 py-2.5 align-baseline text-[13.5px] leading-[1.47] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]'
const TD_MU = 'px-8 py-2.5 align-baseline text-[13px] leading-[1.47] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2'
const TD_R = 'px-8 py-2.5 align-baseline text-[13px] leading-[1.47] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums'
const TR = 'hover:[&>td]:bg-sunk'

/* crm.css .hrow und die Ueberschrift, die darin steht. */
const HROW = 'flex items-center gap-3 px-8 pt-[22px] max-[640px]:px-5 max-[640px]:pt-4'
const H1 = 'm-0 mt-0.5 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em] max-[640px]:text-[25px] max-[640px]:leading-[1.08]'

/* crm.css .btn — die gefuellte Aktion. */
const BTN = `inline-flex cursor-pointer items-center gap-1.5 rounded-[var(--r-pill)] border-0 px-3.5 py-1.5 text-[12.5px] leading-[1.47] font-medium tracking-[-0.01em] whitespace-nowrap no-underline transition-[filter,background-color] duration-[.12s] ease-out bg-action text-white hover:brightness-[1.08]`
</script>

<template>
  <AdminLayout active="kontakte">
    <div data-tokens="crm">
      <div :class="HROW">
        <h1 :class="H1">{{ $t('contacts.title') }}</h1>
      </div>

      <table v-if="contacts.length" class="mt-5 w-full border-collapse">
        <thead>
          <tr>
            <th :class="TH">{{ $t('contacts.colName') }}</th>
            <th :class="TH">{{ $t('contacts.colReach') }}</th>
            <th :class="TH">{{ $t('contacts.colProjects') }}</th>
            <th :class="TH_R">{{ $t('contacts.colNext') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in groups" :key="group.key">
            <tr><td class="bg-sunk px-8 pt-4 pb-1.5 align-baseline text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2" colspan="4">{{ $t(`contacts.group.${group.key}`) }}</td></tr>
            <tr v-for="contact in group.rows" :key="contact.id" :class="TR">
              <td :class="TD_NM"><Link :href="`/kontakte/${contact.id}`">{{ contact.name }}</Link></td>
              <td :class="TD_MU">{{ [contact.email, contact.phone].filter(Boolean).join(' · ') || '—' }}</td>
              <td :class="TD_MU">
                {{ contact.latestProject ?? '—' }}
                <template v-if="contact.projectCount > 1"> · {{ $t('contacts.projectCount', contact.projectCount) }}</template>
              </td>
              <td :class="TD_R">{{ contact.nextDate ? day(contact.nextDate) : '—' }}</td>
            </tr>
          </template>
        </tbody>
      </table>

      <Link v-else :class="[BTN, 'mt-5']" href="/anfragen/neu">
        {{ $t('leads.createKicker') }}
      </Link>
    </div>
  </AdminLayout>
</template>
