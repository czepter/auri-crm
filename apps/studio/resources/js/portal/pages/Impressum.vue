<script setup lang="ts">
import { computed } from 'vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'

// § 5 TMG. Reads straight through StudioSettingsResolver's T-02 columns —
// the same particulars the `studio.*` merge fields already
// read (docs/documents.md) — never a second, independently-maintained copy.
// Labels reuse the `settings.*` strings T-02's own admin form already
// carries (Firmierung, Straße & Hausnummer, ...) rather than a duplicate
// set: one JSON file, one i18n instance, shared by both the admin and
// portal bundles.
const props = defineProps<{
  name: string | null
  legalForm: string | null
  street: string | null
  postcode: string | null
  city: string | null
  country: string | null
  taxNumber: string | null
  vatId: string | null
  registerNumber: string | null
  registerCourt: string | null
  representative: string | null
  website: string | null
}>()

// A legal notice is a list of particulars, not nine cards. Empty ones are
// absent rather than blank: what a studio has not filed is not a gap on this
// page, it is simply not one of its particulars.
const facts = computed(() => [
  { key: 'settings.legalForm', value: props.legalForm },
  { key: 'settings.street', value: props.street },
  { key: 'settings.city', value: [props.postcode, props.city].filter(Boolean).join(' ') || null },
  { key: 'settings.country', value: props.country },
  { key: 'settings.representative', value: props.representative },
  { key: 'settings.taxNumber', value: props.taxNumber },
  { key: 'settings.vatId', value: props.vatId },
  { key: 'settings.registerNumber', value: props.registerNumber },
  { key: 'settings.registerCourt', value: props.registerCourt },
  { key: 'settings.website', value: props.website },
].filter((fact) => fact.value))
</script>

<template>
  <PortalShell>
    <PortalHeader />

    <main class="mx-auto w-full max-w-[668px] grow px-6 pt-[var(--band)] pb-28">
      <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ $t('portal.impressumTitle') }}</p>
      <h1 v-if="name" class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
        {{ name }}
      </h1>

      <dl class="animate-rise [animation-delay:230ms] motion-reduce:animate-none m-0 mt-12 grid grid-cols-[148px_1fr] gap-x-6 gap-y-3 border-t border-border pt-8 max-[700px]:grid-cols-1 max-[700px]:gap-y-0">
        <template v-for="fact in facts" :key="fact.key">
          <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t(fact.key) }}</dt>
          <dd class="m-0 text-[14.5px] tabular-nums">{{ fact.value }}</dd>
        </template>
      </dl>
    </main>

    <PortalFooter />
  </PortalShell>
</template>
