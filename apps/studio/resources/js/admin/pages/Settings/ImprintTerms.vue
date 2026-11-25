<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import SettingsNav from '@/admin/components/settings/SettingsNav.vue'
import LegalTextFields from './panes/LegalTextFields.vue'
import { Button } from '@/ui/button'
import { saveState } from '@/admin/lib/autosave'

const props = defineProps<{
  settings: App.Settings.Data.PortalSettingsData
  legalTemplates: { family: string; name: string; updatedAt: string | null }[]
}>()

const form = useForm({
  portal_legal_impressum_url: props.settings.impressumUrl ?? '',
  portal_legal_datenschutz_url: props.settings.datenschutzUrl ?? '',
  portal_legal_agb_url: props.settings.agbUrl ?? '',
  portal_legal_widerruf_url: props.settings.widerrufUrl ?? '',
  portal_legal_impressum_family: props.settings.legalFamilies.impressum ?? '',
  portal_legal_datenschutz_family: props.settings.legalFamilies.datenschutz ?? '',
  portal_legal_agb_family: props.settings.legalFamilies.agb ?? '',
  portal_legal_widerruf_family: props.settings.legalFamilies.widerruf ?? '',
  portal_legal_agb_attach: props.settings.agbAttach,
  portal_legal_widerruf_attach: props.settings.widerrufAttach,
})

function submit() {
  form
    .transform((data) => ({
      ...data,
      portal_legal_impressum_url: data.portal_legal_impressum_url || null,
      portal_legal_datenschutz_url: data.portal_legal_datenschutz_url || null,
      portal_legal_agb_url: data.portal_legal_agb_url || null,
      portal_legal_widerruf_url: data.portal_legal_widerruf_url || null,
      portal_legal_impressum_family: data.portal_legal_impressum_family || null,
      portal_legal_datenschutz_family: data.portal_legal_datenschutz_family || null,
      portal_legal_agb_family: data.portal_legal_agb_family || null,
      portal_legal_widerruf_family: data.portal_legal_widerruf_family || null,
    }))
    .patch('/einstellungen/impressum-agb', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <FormShell
      :kicker="$t('settings.kicker')" :title="$t('settings.navImprintTerms')"
      :state="saveState([form])"
    >
      <template #head>
        <Button
          v-if="form.isDirty"
          variant="default"
          type="submit"
          form="imprint-terms-form"
          :disabled="form.processing"
        >
          {{ $t('common.save') }}
        </Button>
      </template>

      <template #nav><SettingsNav active="imprint-terms" /></template>

      <form id="imprint-terms-form" class="min-w-0" novalidate @submit.prevent="submit">
        <LegalTextFields :form="form" :families="settings.legalFamilies" :legal-templates="legalTemplates" />
      </form>
    </FormShell>
  </AdminLayout>
</template>
