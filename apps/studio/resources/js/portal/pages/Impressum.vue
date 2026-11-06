<script setup lang="ts">
import { computed } from 'vue'
import PortalBack from '@/portal/components/PortalBack.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'

// § 5 TMG. Reads straight through StudioSettings::current()'s T-02 columns —
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
  email: string | null
  phone: string | null
}>()

const registry = computed(() => [
  { key: 'settings.taxNumber', value: props.taxNumber },
  { key: 'settings.vatId', value: props.vatId },
  { key: 'settings.registerNumber', value: props.registerNumber },
  { key: 'settings.registerCourt', value: props.registerCourt },
].filter(fact => fact.value))
</script>

<template>
  <PortalShell>
    <PortalHeader />

    <main class="mx-auto w-full max-w-[668px] grow px-6 pt-[var(--band)] pb-28">
      <PortalBack />
      <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ $t('portal.impressumTitle') }}</p>
      <h1 v-if="name" class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
        {{ name }}
      </h1>

      <address class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 border-t border-border pt-8 text-[15.5px] leading-[1.6] not-italic">
        <p v-if="legalForm">{{ legalForm }}</p>
        <p v-if="representative">{{ representative }}</p>
        <p v-if="street">{{ street }}</p>
        <p v-if="postcode || city">{{ [postcode, city].filter(Boolean).join(' ') }}</p>
        <p v-if="country">{{ country }}</p>

        <p v-if="email || phone || website" class="mt-4 grid">
          <a v-if="email" class="inline-flex min-h-11 items-center underline" :href="`mailto:${email}`">{{ email }}</a>
          <a v-if="phone" class="inline-flex min-h-11 items-center underline" :href="`tel:${phone.replace(/[^\d+]/g, '')}`">{{ phone }}</a>
          <a v-if="website" class="inline-flex min-h-11 items-center underline" :href="/^https?:/.test(website) ? website : `https://${website}`" target="_blank" rel="noopener">{{ website }}</a>
        </p>
      </address>

      <dl v-if="registry.length" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none m-0 mt-6 grid grid-cols-[148px_1fr] gap-x-6 gap-y-2 text-[14.5px] max-[700px]:grid-cols-1 max-[700px]:gap-y-0">
        <template v-for="fact in registry" :key="fact.key">
          <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t(fact.key) }}</dt>
          <dd class="m-0 tabular-nums">{{ fact.value }}</dd>
        </template>
      </dl>
    </main>

    <PortalFooter />
  </PortalShell>
</template>
