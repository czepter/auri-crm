<script setup lang="ts">
import PortalBack from '@/portal/components/PortalBack.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'

defineProps<{
  showSwitcher: boolean
  project: { id: string; name: string }
  kind: 'payments' | 'contract'
}>()
</script>

<template>
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: showSwitcher }" />

    <PortalWrap class="pb-40">
      <section class="pt-[var(--band)]">
        <h1 class="font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t(`portal.missing.${kind}.title`) }}
        </h1>
        <p class="mt-4 text-[15px] text-muted-foreground">{{ $t(`portal.missing.${kind}.line`) }}</p>
        <div class="mt-6">
          <PortalBack :href="`/projekte/${project.id}`" :label="$t('portal.missing.back')" />
        </div>
      </section>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
