<script setup lang="ts">
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'

// T-08: rendered by EnsurePortalMaintenanceClosure instead of whatever route
// was actually requested — no project, quote or payment data ever
// reaches this component, because the middleware never calls the controller
// that would have supplied it. `message` is the studio's own free text
// (Settings/Portal.vue); falls back to a fixed, deliberately neutral default
// when blank, never invented per-request.
defineProps<{ message: string | null }>()
</script>

<template>
  <PortalShell>
    <PortalHeader />

    <PortalWrap class="flex items-center py-24">
      <div class="animate-rise motion-reduce:animate-none w-full max-w-[620px]">
        <h1 class="font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.maintenanceTitle') }}
        </h1>
        <p class="mt-6 max-w-[52ch] text-[17px] leading-[1.55] text-muted-foreground">{{ message ?? $t('portal.maintenanceDefaultMessage') }}</p>
      </div>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
