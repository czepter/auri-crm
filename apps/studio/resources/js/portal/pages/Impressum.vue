<script setup lang="ts">
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'

// § 5 TMG. Reads straight through StudioSettingsResolver's T-02 columns —
// the same particulars RenderContractJob's `studio.*` merge fields already
// read (docs/contracts.md) — never a second, independently-maintained copy.
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

    <div class="kv" style="margin-top: var(--s5)">
      <div v-if="legalForm" class="kvrow"><span>{{ $t('settings.legalForm') }}</span><span>{{ legalForm }}</span></div>
      <div v-if="street" class="kvrow"><span>{{ $t('settings.street') }}</span><span>{{ street }}</span></div>
      <div v-if="postcode || city" class="kvrow">
        <span>{{ $t('settings.city') }}</span><span>{{ [postcode, city].filter(Boolean).join(' ') }}</span>
      </div>
      <div v-if="country" class="kvrow"><span>{{ $t('settings.country') }}</span><span>{{ country }}</span></div>
      <div v-if="representative" class="kvrow">
        <span>{{ $t('settings.representative') }}</span><span>{{ representative }}</span>
      </div>
      <div v-if="taxNumber" class="kvrow"><span>{{ $t('settings.taxNumber') }}</span><span>{{ taxNumber }}</span></div>
      <div v-if="vatId" class="kvrow"><span>{{ $t('settings.vatId') }}</span><span>{{ vatId }}</span></div>
      <div v-if="registerNumber" class="kvrow">
        <span>{{ $t('settings.registerNumber') }}</span><span>{{ registerNumber }}</span>
      </div>
      <div v-if="registerCourt" class="kvrow">
        <span>{{ $t('settings.registerCourt') }}</span><span>{{ registerCourt }}</span>
      </div>
      <div v-if="website" class="kvrow"><span>{{ $t('settings.website') }}</span><span>{{ website }}</span></div>
    </div>
  </main>

  <PortalFooter />
</template>
