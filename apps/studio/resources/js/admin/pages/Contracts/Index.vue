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
import { dayWithWeekday } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{ contracts: App.Contracts.Data.ContractRowData[] }>()
useLiveProps(['contracts'])

const query = ref('')

const shown = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')

  return props.contracts.filter((contract) =>
    !needle
    || `${contract.projectName} ${contract.contact ?? ''}`.toLocaleLowerCase('de').includes(needle))
})
</script>

<template>
  <AdminLayout active="vertraege">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('contractsOverview.title')" />

        <template v-if="contracts.length">
          <div class="flex flex-wrap items-center gap-3 px-6 pt-4.5">
            <Input v-model="query" class="h-8 w-64" type="search" :placeholder="$t('contractsOverview.searchPlaceholder')" />
          </div>

          <Table>
            <thead>
              <tr>
                <TableHead>{{ $t('contractsOverview.project') }}</TableHead>
                <TableHead>{{ $t('contractsOverview.version') }}</TableHead>
                <TableHead>{{ $t('contractsOverview.sentOn') }}</TableHead>
                <TableHead>{{ $t('contractsOverview.signedOn') }}</TableHead>
                <TableHead>{{ $t('contractsOverview.deadline') }}</TableHead>
              </tr>
            </thead>
            <tbody>
              <TableRow v-for="contract in shown" :key="contract.id">
                <TableCell tone="name">
                  <Link :href="`/auftraege/${contract.projectId}/vertrag`">{{ contract.contact }}</Link>
                  <span class="block text-small text-ink-2">{{ contract.projectName }}</span>
                </TableCell>
                <TableCell class="whitespace-nowrap">
                  <a v-if="contract.hasDocument" :href="`/vertraege/${contract.id}/pdf`" target="_blank">
                    {{ $t('contractsOverview.versionLabel', { version: contract.version }) }}
                  </a>
                  <template v-else>{{ $t('contractsOverview.versionLabel', { version: contract.version }) }}</template>
                </TableCell>
                <TableCell class="whitespace-nowrap">{{ contract.sentOn ? dayWithWeekday(contract.sentOn) : '' }}</TableCell>
                <TableCell class="whitespace-nowrap" :tone="contract.signedOn ? undefined : 'muted'">
                  {{ contract.signedOn ? dayWithWeekday(contract.signedOn) : $t('contractsOverview.open') }}
                </TableCell>
                <TableCell class="whitespace-nowrap" tone="muted">
                  {{ contract.deadlineOn ? dayWithWeekday(contract.deadlineOn) : '' }}
                </TableCell>
              </TableRow>
            </tbody>
          </Table>
        </template>

        <Empty v-else class="mx-8 mt-7" :text="$t('contractsOverview.empty')">
          <Button :as="Link" variant="pill" href="/projekte">{{ $t('contractsOverview.toProjects') }}</Button>
        </Empty>
      </div>
    </Page>
  </AdminLayout>
</template>
