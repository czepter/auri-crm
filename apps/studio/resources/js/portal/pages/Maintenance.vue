<script setup lang="ts">
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'

// T-08: rendered by EnsurePortalMaintenanceClosure instead of whatever route
// was actually requested — no project, quote, contract or payment data ever
// reaches this component, because the middleware never calls the controller
// that would have supplied it. `message` is the studio's own free text
// (Settings/Portal.vue); falls back to a fixed, deliberately neutral default
// when blank, never invented per-request.
defineProps<{ message: string | null }>()
</script>

<template>
  <PortalHeader />

  <main class="pwrap">
    <div class="psec" style="margin-top: var(--s6)">
      <h1>{{ $t('portal.maintenanceTitle') }}</h1>
      <p class="plead">{{ message ?? $t('portal.maintenanceDefaultMessage') }}</p>
    </div>
  </main>

  <PortalFooter />
</template>
