<script setup lang="ts">
import { computed, ref } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import PortalForm from './panes/PortalForm.vue'
import PortalPreview from './previews/PortalPreview.vue'
import { Button } from '@/ui/button'

const props = defineProps<{
  settings: App.Settings.Data.PortalSettingsData
  branding: App.Settings.Data.StudioSettingsData
}>()

const formRef = ref<InstanceType<typeof PortalForm>>()

const preview = computed(() => {
  const form = formRef.value?.form

  return {
    maintenanceEnabled: form?.portal_maintenance_enabled ?? props.settings.maintenanceEnabled,
    maintenanceMessage: form?.portal_maintenance_message ?? props.settings.maintenanceMessage ?? '',
    welcomeText: form?.portal_welcome_text ?? props.settings.welcomeText ?? '',
    acceptQuotes: form?.portal_accept_quotes_enabled ?? props.settings.acceptQuotesEnabled,
    signContracts: form?.portal_sign_contracts_enabled ?? props.settings.signContractsEnabled,
    paymentStatus: form?.portal_payment_status_enabled ?? props.settings.paymentStatusEnabled,
    messagingMode: form?.portal_messaging_mode ?? props.settings.messagingMode,
    contactPhone: form?.portal_contact_phone ?? props.settings.contactPhone ?? '',
    contactEmail: form?.portal_contact_email ?? props.settings.contactEmail ?? '',
    contactResponseTime: form?.portal_contact_response_time ?? props.settings.contactResponseTime ?? '',
    datenschutzUrl: form?.portal_legal_datenschutz_url ?? props.settings.datenschutzUrl ?? '',
    agbUrl: form?.portal_legal_agb_url ?? props.settings.agbUrl ?? '',
  }
})
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="flex items-center gap-4">
      <div>
        <div class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('portalSettings.title') }}</h1>
      </div>
      <span v-if="formRef?.form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
      <Button
        type="submit"
        form="portal-settings-form"
        :disabled="!formRef?.form.isDirty || formRef?.form.processing"
        :style="!formRef?.form.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </Button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <SettingsNav active="portal" />

      <div style="flex: 1; min-width: 0; max-width: 620px">
        <PortalForm ref="formRef" :settings="settings" />
      </div>

      <PortalPreview :branding="branding" :portal="preview" />
    </div>
  </AdminLayout>
</template>
