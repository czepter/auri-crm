<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

defineProps<{ packages: App.Catalog.Data.PackageData[] }>()

const money = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })

function toggle(pkg: App.Catalog.Data.PackageData) {
  router.post(`/pakete/${pkg.id}/status`, {}, { preserveScroll: true })
}

function total(pkg: App.Catalog.Data.PackageData) {
  return pkg.items.reduce((sum, item) => sum + (item.unitPrice ?? 0) * item.quantity, 0)
}
</script>

<template>
  <AdminLayout active="pakete">
    <div data-theme="crm">
      <div class="hrow">
        <h1>{{ $t('packages.title') }}</h1>
        <Link class="btn" href="/pakete/neu" style="margin-left: auto">{{ $t('packages.create') }}</Link>
      </div>

      <!-- C-15 (#251): the line items stand in the list rather than behind a
           click — a package is chosen by what is in it. -->
      <div v-if="packages.length" class="sheet" style="max-width: 900px">
        <div v-for="pkg in packages" :key="pkg.id" class="pkg">
          <header>
            <h3><Link :href="`/pakete/${pkg.id}`">{{ pkg.name }}</Link></h3>
            <span v-if="!pkg.isActive" class="off">{{ $t('packages.inactive') }}</span>
            <span class="sum">{{ money.format(total(pkg) / 100) }}</span>
          </header>

          <div class="quote">
            <div v-for="item in pkg.items" :key="item.id" class="qr">
              <span class="ti">
                {{ item.title }}
                <span v-if="item.description" class="s"> · {{ item.description }}</span>
              </span>
              <span class="qty">{{ item.quantity }}×</span>
              <!-- A position without a price is an included service, not a
                   zero: package_items.unit_price is nullable for exactly that. -->
              <span class="amt">
                {{ item.unitPrice === null ? $t('packages.included') : money.format(item.unitPrice / 100) }}
              </span>
            </div>
          </div>

          <button class="btn-2" type="button" style="margin-top: var(--s3)" @click="toggle(pkg)">
            {{ pkg.isActive ? $t('packages.deactivate') : $t('packages.activate') }}
          </button>
        </div>
      </div>

      <p v-else class="hint" style="padding: var(--s5) var(--s7)">{{ $t('packages.empty') }}</p>
    </div>
  </AdminLayout>
</template>
