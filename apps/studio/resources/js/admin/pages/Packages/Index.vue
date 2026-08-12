<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Button } from '@/ui/button'

defineProps<{ packages: App.Catalog.Data.PackageData[] }>()

function toggle(pkg: App.Catalog.Data.PackageData) {
  router.post(`/pakete/${pkg.id}/status`, {}, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="pakete">
    <div class="hrow" style="margin-bottom: var(--s5)">
      <h1>{{ $t('packages.title') }}</h1>
      <Button :as="Link" href="/pakete/neu">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('packages.create') }}
      </Button>
    </div>

    <!-- No mockup for this screen. It follows the system: the labelled row
         grammar from the contacts and team lists, not a data table. -->
    <div v-if="packages.length" class="panel">
      <div v-for="pkg in packages" :key="pkg.id" class="trow">
        <Link class="pinfo" :href="`/pakete/${pkg.id}`">
          <span class="pname">{{ pkg.name }}</span>
          <span class="psub">{{ $t('packages.itemCount', pkg.itemCount) }}</span>
        </Link>
        <span class="chip" :class="pkg.isActive ? 'done' : 'off'">
          {{ pkg.isActive ? $t('packages.active') : $t('packages.inactive') }}
        </span>
        <Button type="button" variant="outline" @click="toggle(pkg)">
          {{ pkg.isActive ? $t('packages.deactivate') : $t('packages.activate') }}
        </Button>
      </div>
    </div>

    <p v-else class="hint">{{ $t('packages.empty') }}</p>
  </AdminLayout>
</template>
