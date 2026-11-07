<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import PortalBack from '@/portal/components/PortalBack.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { Button } from '@/ui/button'

defineProps<{
  showSwitcher: boolean
  project: { id: string; name: string }
  kind: 'payments' | 'contract'
  quote: { accepted: boolean } | null
}>()
</script>

<template>
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: showSwitcher }" />

    <PortalWrap class="pb-10">
      <section class="pt-[var(--band)]">
        <PortalBack :href="`/projekte/${project.id}`" />
        <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.name }}</p>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t(`portal.missing.${kind}.title`) }}
        </h1>
        <p class="mt-4 text-[15px] text-muted-foreground">{{ $t(`portal.missing.${kind}.line`) }}</p>
        <Button v-if="quote" as-child variant="portal" class="mt-8 min-h-12">
          <Link :href="`/projekte/${project.id}/angebot`">{{ quote.accepted ? $t('portal.quoteView') : $t('portal.quoteAccept') }}</Link>
        </Button>
      </section>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
