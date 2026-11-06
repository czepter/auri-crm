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

const signed = computed(() => shown.value.filter((contract) => contract.signedOn).length)
</script>

<template>
  <AdminLayout active="vertraege">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('contractsOverview.title')" />

        <div v-if="contracts.length" class="flex items-start gap-10 max-[900px]:flex-col max-[900px]:gap-0">
          <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-3 px-6 pt-4.5">
            <Input v-model="query" class="h-8 min-w-60 w-64 shrink-0" type="search" :placeholder="$t('contractsOverview.searchPlaceholder')" />
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
          </div>

          <aside class="sticky top-6 mt-4.5 mr-6 w-[320px] flex-none max-[900px]:mx-6 max-[900px]:w-auto">
            <dl class="grid gap-3 text-small">
              <div class="flex justify-between gap-4"><dt class="text-ink-2">{{ $t('contractsOverview.signedOn') }}</dt><dd class="tabular-nums">{{ signed }}</dd></div>
              <div class="flex justify-between gap-4"><dt class="text-ink-2">{{ $t('contractsOverview.open') }}</dt><dd class="tabular-nums">{{ shown.length - signed }}</dd></div>
            </dl>
          </aside>
        </div>

        <Empty v-else :text="$t('contractsOverview.empty')">
          <Button :as="Link" variant="pill" href="/projekte">{{ $t('contractsOverview.toProjects') }}</Button>
        </Empty>
      </div>
    </Page>
  </AdminLayout>
</template>
