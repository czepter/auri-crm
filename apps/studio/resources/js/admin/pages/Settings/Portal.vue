<script setup lang="ts">
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import PortalForm from './panes/PortalForm.vue'
import PortalPreview from './previews/PortalPreview.vue'
import { Button } from '@/ui/button'

const props = defineProps<{
  settings: App.Settings.Data.PortalSettingsData
  branding: App.Settings.Data.StudioSettingsData
}>()

// Same query-pane shell as Settings/Index.vue, for the same reason: the rail
// switches between them from every other settings screen too.
const PANES = ['portal', 'home', 'privacy'] as const

const page = usePage()

const pane = computed(() => {
  const asked = new URL(page.url, 'http://settings').searchParams.get('pane')

  return PANES.find((known) => known === asked) ?? 'portal'
})

const paneTitle = computed(() => {
  if (pane.value === 'home') return 'portalSettings.homeSection'
  if (pane.value === 'privacy') return 'portalSettings.dsgvoSection'
  return 'portalSettings.title'
})

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
    <div data-tokens>
      <PageHead :kicker="$t('settings.kicker')" :title="$t(paneTitle)">
        <span v-if="formRef?.form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
        <Button
          class="ml-auto"
          variant="pill"
          type="submit"
          form="portal-settings-form"
          :disabled="!formRef?.form.isDirty || formRef?.form.processing"
        >
          {{ $t('common.save') }}
        </Button>
      </PageHead>

      <div class="px-8 pt-7 pb-8">
        <div class="flex items-start gap-8">
          <SettingsNav :active="pane" />

          <div class="max-w-[620px] min-w-0 flex-1">
            <PortalForm :key="pane" ref="formRef" :settings="settings" :sections="[pane]" />
          </div>

          <PortalPreview :branding="branding" :portal="preview" />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
