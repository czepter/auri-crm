<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
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

/* crm.css .hrow und die Ueberschrift, die darin steht. */
</script>

<template>
  <AdminLayout active="benachrichtigungen">
    <div data-tokens="crm">
      <PageHead :title="$t('notifications.title')">
        <Button
          v-if="unread > 0"
          class="ml-auto"
          type="button"
          variant="ghost"
          @click="router.post('/benachrichtigungen/gelesen', {}, { preserveScroll: true })"
        >
          {{ $t('notifications.markRead') }}
        </Button>
      </PageHead>

      <Table v-if="notifications.length">
        <thead>
          <tr>
            <TableHead>{{ $t('notifications.colEvent') }}</TableHead>
            <TableHead>{{ $t('notifications.colWhat') }}</TableHead>
            <TableHead align="right">{{ $t('notifications.colWhen') }}</TableHead>
          </tr>
        </thead>
        <tbody>
          <TableRow v-for="row in notifications" :key="row.id">
            <!-- Read is the muted row, the same weight drop the dashboard's
                 own settled rows take. -->
            <TableCell :tone="row.readAt === null ? 'name' : 'muted'">
              <Link v-if="row.url" :href="row.url">{{ row.title }}</Link>
              <template v-else>{{ row.title }}</template>
            </TableCell>
            <TableCell tone="muted">{{ row.body }}</TableCell>
            <TableCell tone="muted" align="right">{{ relativeDate(row.createdAt.slice(0, 10), locale) }}</TableCell>
          </TableRow>
        </tbody>
      </Table>
    </div>
  </AdminLayout>
</template>
