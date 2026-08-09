<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

defineProps<{ leads: number; jobs: number }>()

// The dashboard is behind audience:staff, so the shared user is never null here.
const user = computed(() => usePage().props.auth.user!)
</script>

<template>
  <AdminLayout active="uebersicht">
    <div class="hrow">
      <div>
        <h1>{{ $t('dashboard.title') }}</h1>
        <div class="dsub">{{ $t('dashboard.signedInAs', { name: user.name }) }}</div>
      </div>
    </div>

    <!-- The Übersicht fills progressively: S-01 owns its columns in sprint 4,
         and only the numbers with a data source behind them appear now. Two
         real counts beat four tiles where half are invented. -->
    <div class="kpis" style="grid-template-columns: repeat(2, 1fr); max-width: 460px">
      <Link class="kpi" href="/anfragen">
        <b>{{ leads }}</b>
        {{ $t('dashboard.openLeads') }}
      </Link>
      <div class="kpi">
        <b>{{ jobs }}</b>
        {{ $t('dashboard.activeJobs') }}
      </div>
    </div>
  </AdminLayout>
</template>
