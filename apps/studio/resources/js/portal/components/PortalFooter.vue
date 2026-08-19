<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

type Branding = { displayName: string | null }
type Legal = { impressumUrl: string; datenschutzUrl: string | null; agbUrl: string | null }

// T-08's own legal links, shared portal-wide by HandleInertiaRequests
// (`portalLegal`) — the same footer on every screen, including the
// Maintenance closure itself (a portal a studio has closed still needs its
// Impressum reachable from the page a client is looking at).
const branding = computed(() => usePage().props.portalBranding as Branding | undefined)
const legal = computed(() => usePage().props.portalLegal as Legal | undefined)
</script>

<template>
  <footer
    class="mx-auto flex max-w-[740px] flex-wrap gap-5 px-8 pb-11 text-[12.5px] leading-none font-normal text-faint"
  >
    <span v-if="branding?.displayName">{{ branding.displayName }}</span>
    <a class="text-faint no-underline hover:text-muted-foreground" :href="legal?.impressumUrl ?? '/impressum'">{{ $t('portal.footerImpressum') }}</a>
    <a v-if="legal?.datenschutzUrl" class="text-faint no-underline hover:text-muted-foreground" :href="legal.datenschutzUrl" target="_blank" rel="noopener">
      {{ $t('portal.footerDatenschutz') }}
    </a>
    <a v-if="legal?.agbUrl" class="text-faint no-underline hover:text-muted-foreground" :href="legal.agbUrl" target="_blank" rel="noopener">{{ $t('portal.footerAgb') }}</a>
  </footer>
</template>
