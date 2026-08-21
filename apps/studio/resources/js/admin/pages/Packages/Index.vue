<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'
import { formatEurosRounded } from '@/admin/lib/money'

defineProps<{ packages: App.Catalog.Data.PackageData[] }>()


function toggle(pkg: App.Catalog.Data.PackageData) {
  router.post(`/pakete/${pkg.id}/status`, {}, { preserveScroll: true })
}

function total(pkg: App.Catalog.Data.PackageData) {
  return pkg.items.reduce((sum, item) => sum + (item.unitPrice ?? 0) * item.quantity, 0)
}

/* crm.css .btn / .btn-2 — the filled action and its grey twin. */
</script>

<template>
  <AdminLayout active="pakete">
    <div data-tokens>
      <PageHead :title="$t('packages.title')">
        <Button :as="Link" variant="pill" class="ml-auto" href="/pakete/neu">{{ $t('packages.create') }}</Button>
      </PageHead>

      <!-- C-15 (#251): the line items stand in the list rather than behind a
           click — a package is chosen by what is in it. -->
      <div v-if="packages.length" class="max-w-[900px] px-8 pt-7 pb-8">
        <div v-for="pkg in packages" :key="pkg.id" class="pt-3.5 pb-4 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
          <header class="mb-2 flex items-baseline gap-2.5">
            <h3 class="m-0 text-[15px] leading-[1.2] font-semibold tracking-[-0.018em]">
              <Link :href="`/pakete/${pkg.id}`">{{ pkg.name }}</Link>
            </h3>
            <span v-if="!pkg.isActive" class="text-[11.5px] text-ink-2">{{ $t('packages.inactive') }}</span>
            <span class="ml-auto text-[13px] font-semibold tabular-nums">{{ formatEurosRounded(total(pkg)) }}</span>
          </header>

          <div>
            <div v-for="item in pkg.items" :key="item.id" class="flex items-baseline gap-3.5 py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none">
              <span class="min-w-0 flex-1 text-[13.5px] leading-[1.47]">
                {{ item.title }}
                <span v-if="item.description" class="text-[12px] text-ink-2"> · {{ item.description }}</span>
              </span>
              <span class="w-14 flex-none text-right text-[12.5px] leading-[1.47] tabular-nums text-ink-2">{{ item.quantity }}×</span>
              <!-- A position without a price is an included service, not a
                   zero: package_items.unit_price is nullable for exactly that. -->
              <span class="w-28 flex-none text-right text-[13px] leading-[1.47] tabular-nums">
                {{ item.unitPrice === null ? $t('packages.included') : formatEurosRounded(item.unitPrice) }}
              </span>
            </div>
          </div>

          <Button variant="pill-muted" class="mt-3" type="button" @click="toggle(pkg)">
            {{ pkg.isActive ? $t('packages.deactivate') : $t('packages.activate') }}
          </Button>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
