<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { cn } from '@/lib/utils'

type Branding = { displayName: string | null; address: string | null }
type Legal = { impressumUrl: string; datenschutzUrl: string | null; agbUrl: string | null }

/*
 * mockups/redesign/tokens.css `.foot`. The studio on the left, the law on the
 * right, one rule above both — and the same measure as every other screen, so
 * the page has one edge instead of two.
 *
 * Shared portal-wide by HandleInertiaRequests (`portalBranding`,
 * `portalLegal`), which is what puts the same footer under every screen
 * including the maintenance closure: a portal a studio has shut still needs
 * its Impressum reachable from the page a client is looking at.
 */
const props = defineProps<{ class?: HTMLAttributes['class'] }>()

const branding = computed(() => usePage().props.portalBranding as Branding | undefined)
const legal = computed(() => usePage().props.portalLegal as Legal | undefined)

const who = computed(() =>
  [branding.value?.displayName, branding.value?.address].filter(Boolean).join(' · '),
)
</script>

<template>
  <footer
    :class="cn('mx-auto flex w-full max-w-[var(--page)] flex-wrap justify-between gap-6 border-t border-border px-6 pt-8 pb-18 text-[13px] leading-[1.5] text-muted-foreground', props.class)"
  >
    <span>{{ who }}</span>
    <span class="flex flex-wrap gap-6 [&_a]:no-underline [&_a:hover]:text-foreground">
      <a :href="legal?.impressumUrl ?? '/impressum'">{{ $t('portal.footerImpressum') }}</a>
      <a v-if="legal?.datenschutzUrl" :href="legal.datenschutzUrl" target="_blank" rel="noopener">
        {{ $t('portal.footerDatenschutz') }}
      </a>
      <a v-if="legal?.agbUrl" :href="legal.agbUrl" target="_blank" rel="noopener">{{ $t('portal.footerAgb') }}</a>
    </span>
  </footer>
</template>
