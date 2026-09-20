<script setup lang="ts">
import { computed, ref } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import PortalForm from './panes/PortalForm.vue'
import PortalPreview from './previews/PortalPreview.vue'
import { Button } from '@/ui/button'

const props = defineProps<{
  settings: App.Settings.Data.PortalSettingsData
  branding: App.Settings.Data.StudioSettingsData
}>()

const formRef = ref<InstanceType<typeof PortalForm>>()

// Welcher der drei Begrüßungstexte in der Vorschau steht — dieselben
// Zustände, die HomeController aus den Buchungen ableitet.
const state = ref<'inquiry' | 'active' | 'done'>('inquiry')

const preview = computed(() => {
  const form = formRef.value?.form

  return {
    maintenanceEnabled: form?.portal_maintenance_enabled ?? props.settings.maintenanceEnabled,
    maintenanceMessage: form?.portal_maintenance_message ?? props.settings.maintenanceMessage ?? '',
    welcomeText: form?.[`portal_welcome_${state.value}`] ?? '',
    acceptQuotes: form?.portal_accept_quotes_enabled ?? props.settings.acceptQuotesEnabled,
    signContracts: form?.portal_sign_contracts_enabled ?? props.settings.signContractsEnabled,
    paymentStatus: form?.portal_payment_status_enabled ?? props.settings.paymentStatusEnabled,
    messagingMode: form?.portal_messaging_mode ?? props.settings.messagingMode,
    contactPhone: form?.portal_contact_phone ?? props.settings.contactPhone ?? '',
    contactEmail: form?.portal_contact_email ?? props.settings.contactEmail ?? '',
    contactResponseTime: form?.portal_contact_response_time ?? props.settings.contactResponseTime ?? '',
    datenschutzUrl: props.settings.datenschutzUrl ?? '',
    agbUrl: props.settings.agbUrl ?? '',
  }
})
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('portalSettings.title')">
          <span v-if="formRef?.form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
          <Button
            v-if="formRef?.form.isDirty"
            class="ml-auto"
            variant="pill"
            type="submit"
            form="portal-settings-form"
            :disabled="formRef?.form.processing"
          >
            {{ $t('common.save') }}
          </Button>
        </PageHead>

        <div class="px-8 pt-7 pb-8">
          <div class="flex flex-wrap items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="portal" />

            <div class="max-w-[620px] min-w-0 flex-1">
              <PortalForm ref="formRef" :settings="settings" />
            </div>

            <PortalPreview v-model:state="state" :branding="branding" :portal="preview" />
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
