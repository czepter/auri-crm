<script setup lang="ts">
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'

// § 5 TMG. Reads straight through StudioSettingsResolver's T-02 columns —
// the same particulars RenderContractJob's `studio.*` merge fields already
// read (docs/documents.md) — never a second, independently-maintained copy.
// Labels reuse the `settings.*` strings T-02's own admin form already
// carries (Firmierung, Straße & Hausnummer, ...) rather than a duplicate
// set: one JSON file, one i18n instance, shared by both the admin and
// portal bundles.
defineProps<{
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
</script>

<template>
  <PortalHeader />

  <PortalWrap>
    <div class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.impressumTitle') }}</div>
    <h1 v-if="name">{{ name }}</h1>

    <div class="mt-8 grid grid-cols-3 gap-3.5">
      <div v-if="legalForm" class="rounded-md bg-muted px-5 pt-3.5 pb-5"><div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('settings.legalForm') }}</div><div class="mt-2.25 text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ legalForm }}</div></div>
      <div v-if="street" class="rounded-md bg-muted px-5 pt-3.5 pb-5"><div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('settings.street') }}</div><div class="mt-2.25 text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ street }}</div></div>
      <div v-if="postcode || city" class="rounded-md bg-muted px-5 pt-3.5 pb-5">
        <div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('settings.city') }}</div><div class="mt-2.25 text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ [postcode, city].filter(Boolean).join(' ') }}</div>
      </div>
      <div v-if="country" class="rounded-md bg-muted px-5 pt-3.5 pb-5"><div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('settings.country') }}</div><div class="mt-2.25 text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ country }}</div></div>
      <div v-if="representative" class="rounded-md bg-muted px-5 pt-3.5 pb-5">
        <div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('settings.representative') }}</div><div class="mt-2.25 text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ representative }}</div>
      </div>
      <div v-if="taxNumber" class="rounded-md bg-muted px-5 pt-3.5 pb-5"><div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('settings.taxNumber') }}</div><div class="mt-2.25 text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ taxNumber }}</div></div>
      <div v-if="vatId" class="rounded-md bg-muted px-5 pt-3.5 pb-5"><div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('settings.vatId') }}</div><div class="mt-2.25 text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ vatId }}</div></div>
      <div v-if="registerNumber" class="rounded-md bg-muted px-5 pt-3.5 pb-5">
        <div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('settings.registerNumber') }}</div><div class="mt-2.25 text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ registerNumber }}</div>
      </div>
      <div v-if="registerCourt" class="rounded-md bg-muted px-5 pt-3.5 pb-5">
        <div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('settings.registerCourt') }}</div><div class="mt-2.25 text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ registerCourt }}</div>
      </div>
      <div v-if="website" class="rounded-md bg-muted px-5 pt-3.5 pb-5"><div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('settings.website') }}</div><div class="mt-2.25 text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ website }}</div></div>
    </div>
  </PortalWrap>

  <PortalFooter />
</template>
