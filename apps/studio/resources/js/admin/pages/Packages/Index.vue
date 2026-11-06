<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import { Table, TableCell, TableGroupRow, TableHead, TableRow } from '@/ui/table'
import { Button } from '@/ui/button'
import { formatEurosRounded } from '@/lib/money'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{ packages: App.Catalog.Data.PackageData[] }>()
useLiveProps(['packages'])

// Gruppiert nach dem, was die Pakete unterscheidet: die einen sind Leistungen,
// die ein Bestandskunde selbst anfragen kann, die anderen Vorlagen für
// Angebote. Die Gruppenzeile ersetzt eine Spalte.
const groups = computed(() => [
  { key: 'portal', rows: props.packages.filter((pkg) => pkg.portalOffered) },
  { key: 'quotes', rows: props.packages.filter((pkg) => !pkg.portalOffered) },
].filter((group) => group.rows.length))

const inPortal = computed(() => props.packages.filter((pkg) => pkg.portalOffered).length)
</script>

<template>
  <AdminLayout active="pakete">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead
          :kicker="$t('packages.kicker', { total: packages.length, portal: inPortal })"
          :title="$t('packages.title')"
        >
          <Button v-if="packages.length" :as="Link" variant="pill" class="ml-auto" href="/pakete/neu">{{ $t('packages.create') }}</Button>
        </PageHead>

        <Empty v-if="!packages.length" class="mx-8 mt-7">
          <Button :as="Link" variant="pill" href="/pakete/neu">{{ $t('packages.create') }}</Button>
        </Empty>

        <Table v-else>
          <thead>
            <tr>
              <TableHead class="w-[32%]">{{ $t('packages.colPackage') }}</TableHead>
              <TableHead align="right" class="w-[11%]">{{ $t('packages.colItems') }}</TableHead>
              <TableHead align="right" class="w-[15%]">{{ $t('packages.colPrice') }}</TableHead>
              <TableHead class="w-[21%]">{{ $t('packages.colRequest') }}</TableHead>
              <TableHead align="right" class="w-[21%]">{{ $t('packages.colRequested') }}</TableHead>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in groups" :key="group.key">
              <TableGroupRow :colspan="5">{{ $t(`packages.group.${group.key}`) }}</TableGroupRow>
              <TableRow v-for="pkg in group.rows" :key="pkg.id">
                <TableCell tone="name">
                  <Link :href="`/pakete/${pkg.id}`">{{ pkg.name }}</Link>
                  <span v-if="!pkg.isActive" class="ml-2 text-small font-normal text-ink-2">{{ $t('packages.paused') }}</span>
                </TableCell>
                <TableCell tone="muted" align="right">{{ pkg.itemCount }}</TableCell>
                <TableCell align="right">
                  <template v-if="pkg.portalOffered && pkg.portalPriceMode === 'on_request'">{{ $t('packages.priceOnRequest') }}</template>
                  <template v-else-if="pkg.portalOffered && pkg.portalPriceMode === 'from'">{{ $t('packages.priceFromValue', { price: formatEurosRounded(pkg.price ?? 0) }) }}</template>
                  <template v-else>{{ formatEurosRounded(pkg.price ?? 0) }}</template>
                </TableCell>
                <TableCell tone="muted">
                  <template v-if="!pkg.portalOffered">—</template>
                  <template v-else>{{ pkg.portalRequiresDate ? $t('packages.portalWithDate') : $t('packages.portalWithoutDate') }}</template>
                </TableCell>
                <TableCell tone="muted" align="right">
                  <template v-if="!pkg.requestedCount">—</template>
                  <template v-else>
                    {{ pkg.requestedCount }} · <span class="text-ok">{{ $t('packages.bookedCount', { count: pkg.bookedCount }) }}</span>
                  </template>
                </TableCell>
              </TableRow>
            </template>
          </tbody>
        </Table>
      </div>
    </Page>
  </AdminLayout>
</template>
