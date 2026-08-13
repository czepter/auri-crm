<script setup lang="ts">
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'

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

  <main class="pwrap">
    <div class="kick">{{ $t('portal.impressumTitle') }}</div>
    <h1 v-if="name">{{ name }}</h1>

    <div class="tiles" style="margin-top: var(--s5)">
      <div v-if="legalForm" class="fact"><div class="l">{{ $t('settings.legalForm') }}</div><div class="v">{{ legalForm }}</div></div>
      <div v-if="street" class="fact"><div class="l">{{ $t('settings.street') }}</div><div class="v">{{ street }}</div></div>
      <div v-if="postcode || city" class="fact">
        <div class="l">{{ $t('settings.city') }}</div><div class="v">{{ [postcode, city].filter(Boolean).join(' ') }}</div>
      </div>
      <div v-if="country" class="fact"><div class="l">{{ $t('settings.country') }}</div><div class="v">{{ country }}</div></div>
      <div v-if="representative" class="fact">
        <div class="l">{{ $t('settings.representative') }}</div><div class="v">{{ representative }}</div>
      </div>
      <div v-if="taxNumber" class="fact"><div class="l">{{ $t('settings.taxNumber') }}</div><div class="v">{{ taxNumber }}</div></div>
      <div v-if="vatId" class="fact"><div class="l">{{ $t('settings.vatId') }}</div><div class="v">{{ vatId }}</div></div>
      <div v-if="registerNumber" class="fact">
        <div class="l">{{ $t('settings.registerNumber') }}</div><div class="v">{{ registerNumber }}</div>
      </div>
      <div v-if="registerCourt" class="fact">
        <div class="l">{{ $t('settings.registerCourt') }}</div><div class="v">{{ registerCourt }}</div>
      </div>
      <div v-if="website" class="fact"><div class="l">{{ $t('settings.website') }}</div><div class="v">{{ website }}</div></div>
    </div>
  </main>

  <PortalFooter />
</template>
