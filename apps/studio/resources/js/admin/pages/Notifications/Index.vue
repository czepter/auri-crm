<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Button } from '@/ui/button'
import { relativeDate } from '@/admin/lib/dates'

const props = defineProps<{
  notifications: {
    id: string
    title: string
    body: string
    url: string | null
    readAt: string | null
    createdAt: string
  }[]
}>()

const { locale } = useI18n()

const unread = computed(() => props.notifications.filter((row) => row.readAt === null).length)

/* crm.css's own table, written out. The sheet styled td and th directly, and
   an element rule beats every utility — so the rules are gone and the values
   live here. */
const TH = 'px-8 pt-0 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2'
const TH_R = 'px-8 pt-0 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2 text-right'
const TD = 'px-8 py-2.5 align-baseline text-[13px] leading-[1.47] shadow-[inset_0_1px_0_#F0F0F2]'
const TD_NM = 'px-8 py-2.5 align-baseline text-[13.5px] leading-[1.47] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]'
const TD_MU = 'px-8 py-2.5 align-baseline text-[13px] leading-[1.47] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2'
const TD_R_MU = 'px-8 py-2.5 align-baseline text-[13px] leading-[1.47] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums text-ink-2'
const TR = 'hover:[&>td]:bg-sunk'

/* crm.css .hrow und die Ueberschrift, die darin steht. */
const HROW = 'flex items-center gap-3 px-8 pt-[22px] max-[640px]:px-5 max-[640px]:pt-4'
const H1 = 'm-0 mt-0.5 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em] max-[640px]:text-[25px] max-[640px]:leading-[1.08]'
</script>

<template>
  <AdminLayout active="benachrichtigungen">
    <div data-tokens="crm">
      <div :class="HROW">
        <h1 :class="H1">{{ $t('notifications.title') }}</h1>
        <Button
          v-if="unread > 0"
          class="ml-auto"
          type="button"
          variant="ghost"
          @click="router.post('/benachrichtigungen/gelesen', {}, { preserveScroll: true })"
        >
          {{ $t('notifications.markRead') }}
        </Button>
      </div>

      <table v-if="notifications.length" class="mt-5 w-full border-collapse">
        <thead>
          <tr>
            <th :class="TH">{{ $t('notifications.colEvent') }}</th>
            <th :class="TH">{{ $t('notifications.colWhat') }}</th>
            <th :class="TH_R">{{ $t('notifications.colWhen') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in notifications" :key="row.id" :class="TR">
            <!-- Read is the muted row, the same weight drop the dashboard's
                 own settled rows take. -->
            <td :class="row.readAt === null ? TD_NM : TD_MU">
              <Link v-if="row.url" :href="row.url">{{ row.title }}</Link>
              <template v-else>{{ row.title }}</template>
            </td>
            <td :class="TD_MU">{{ row.body }}</td>
            <td :class="TD_R_MU">{{ relativeDate(row.createdAt.slice(0, 10), locale) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
