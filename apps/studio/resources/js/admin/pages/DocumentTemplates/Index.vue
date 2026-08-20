<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

type Edition = { id: string; version: number; isCurrent: boolean; inUse: number }

defineProps<{
  templates: App.Documents.Data.DocumentTemplateData[]
  families: Record<string, Edition[]>
  type: string | null
  types: { value: string; label: string }[]
}>()

/* crm.css's own table, written out. The sheet styled td and th directly, and
   an element rule beats every utility — so the rules are gone and the values
   live here. */
const TH = 'px-8 pt-0 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2'
const TH_R = 'px-8 pt-0 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2 text-right'
const TD = 'px-8 py-2.5 align-baseline text-[13px] leading-[1.47] shadow-[inset_0_1px_0_#F0F0F2]'
const TD_NM = 'px-8 py-2.5 align-baseline text-[13.5px] leading-[1.47] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]'
const TD_MU = 'px-8 py-2.5 align-baseline text-[13px] leading-[1.47] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2'
const TD_R = 'px-8 py-2.5 align-baseline text-[13px] leading-[1.47] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums'
const TD_R_MU = 'px-8 py-2.5 align-baseline text-[13px] leading-[1.47] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums text-ink-2'
const TR = 'hover:[&>td]:bg-sunk'

/* crm.css .hrow und die Ueberschrift, die darin steht. */
const HROW = 'flex items-center gap-3 px-8 pt-[22px] max-[640px]:px-5 max-[640px]:pt-4'
const H1 = 'm-0 mt-0.5 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em] max-[640px]:text-[25px] max-[640px]:leading-[1.08]'

/* crm.css .btn — die gefuellte Aktion. */
const BTN = `inline-flex cursor-pointer items-center gap-1.5 rounded-[var(--r-pill)] border-0 px-3.5 py-1.5 text-[12.5px] leading-[1.47] font-medium tracking-[-0.01em] whitespace-nowrap no-underline transition-[filter,background-color] duration-[.12s] ease-out bg-action text-white hover:brightness-[1.08]`

/* crm.css .filters / .pop / .link. */
const FILTERS = 'flex items-center gap-2 px-8 pt-4'
const POP = 'inline-flex cursor-pointer items-center gap-1.5 rounded-lg border-0 bg-sunk-2 px-2.5 py-[5px] text-[12.5px] leading-[1.3] font-normal text-foreground [&_u]:text-ink-2 [&_u]:no-underline after:mt-[-4px] after:text-[11px] after:text-ink-2 after:content-[\'⌄\']'
const LINK = 'text-[12.5px] leading-[1.47] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline'
</script>

<template>
  <AdminLayout active="dokumentvorlagen">
    <div data-tokens="crm">
      <div :class="HROW">
        <h1 :class="H1">{{ $t('documentTemplates.title') }}</h1>
        <Link :class="[BTN, 'ml-auto']" href="/dokumentvorlagen/neu">
          {{ $t('documentTemplates.create') }}
        </Link>
      </div>

      <div :class="FILTERS">
        <Link :class="POP" href="/dokumentvorlagen">{{ $t('documentTemplates.allTypes') }}</Link>
        <Link
          v-for="option in types"
          :key="option.value"
          :class="POP"
          :href="`/dokumentvorlagen?type=${option.value}`"
        >
          <u v-if="type === option.value">•</u>{{ option.label }}
        </Link>
      </div>

      <table v-if="templates.length" class="mt-5 w-full border-collapse">
        <thead>
          <tr>
            <th :class="TH">{{ $t('documentTemplates.colName') }}</th>
            <th :class="TH">{{ $t('documentTemplates.colType') }}</th>
            <th :class="TH_R">{{ $t('documentTemplates.colVersion') }}</th>
            <th :class="TH_R">{{ $t('documentTemplates.colInUse') }}</th>
            <th :class="TH_R" />
          </tr>
        </thead>
        <tbody>
          <template v-for="template in templates" :key="template.id">
            <tr :class="TR">
              <td :class="TD_NM"><Link :href="`/dokumentvorlagen/${template.id}`">{{ template.name }}</Link></td>
              <td :class="TD_MU">
                {{ template.typeLabel }}
                <template v-if="template.shootKindLabel"> · {{ template.shootKindLabel }}</template>
              </td>
              <td :class="TD_R">{{ template.version }}</td>
              <td :class="TD_R">{{ families[template.id]?.find((e) => e.isCurrent)?.inUse ?? 0 }}</td>
              <td :class="TD_R">
                <!-- Not "Bearbeiten": overwriting a template that is out
                     would mean a signed contract silently looking different
                     afterwards. -->
                <Link :class="LINK" :href="`/dokumentvorlagen/${template.id}`">
                  {{ $t('documentTemplates.newVersion') }}
                </Link>
              </td>
            </tr>

            <tr :class="TR" v-for="edition in (families[template.id] ?? []).filter((e) => !e.isCurrent)" :key="edition.id">
              <td :class="TD_MU" colspan="2">{{ $t('documentTemplates.version', { version: edition.version }) }}</td>
              <td :class="TD_R_MU">{{ edition.version }}</td>
              <td :class="TD_R_MU">{{ edition.inUse }}</td>
              <td :class="TD" />
            </tr>
          </template>
        </tbody>
      </table>

    </div>
  </AdminLayout>
</template>
