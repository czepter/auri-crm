<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

defineProps<{ projects: App.Crm.Data.ProjectRowData[] }>()

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

/* crm.css's own table, written out. The sheet styled td and th directly, and
   an element rule beats every utility — so the rules are gone and the values
   live here. */
const TH = 'px-8 pt-0 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2'
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
  <AdminLayout active="projekte">
    <!-- The first screen on the CRM sheet (C-01, #235). Its own theme root
         rather than the layout's, so the screens that have not been ported
         keep the stylesheet they were built on. -->
    <div data-tokens="crm">
      <div :class="HROW">
        <h1 :class="H1">{{ $t('projects.title') }}</h1>
      </div>

      <table v-if="projects.length" class="mt-5 w-full border-collapse">
        <thead>
          <tr>
            <th :class="TH">{{ $t('projects.name') }}</th>
            <th :class="TH">{{ $t('project.mainDateLabel') }}</th>
            <th :class="TH">{{ $t('project.venueName') }}</th>
            <th :class="TH">{{ $t('project.position') }}</th>
            <th :class="TH">{{ $t('projects.contact') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="project in projects" :key="project.id" :class="TR">
            <td :class="TD_NM">
              <Link :href="`/auftraege/${project.id}`">{{ project.name }}</Link>
            </td>
            <td :class="TD_R">{{ project.date ? day(project.date) : '—' }}</td>
            <td :class="TD_MU">{{ project.venue ?? '—' }}</td>
            <td :class="TD">{{ project.position }}</td>
            <td :class="TD_MU">{{ project.contact ?? '—' }}</td>
          </tr>
        </tbody>
      </table>

      <Link v-else :class="[BTN, 'mt-5']" href="/anfragen/neu">
        {{ $t('leads.createKicker') }}
      </Link>
    </div>
  </AdminLayout>
</template>
