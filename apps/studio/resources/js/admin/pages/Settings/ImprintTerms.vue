<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import LegalTextFields from './panes/LegalTextFields.vue'
import { Button } from '@/ui/button'

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
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('settings.navImprintTerms')">
          <span v-if="form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-small leading-[1.5] font-semibold bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
          <Button
            v-if="form.isDirty"
            class="ml-auto"
            variant="pill"
            type="submit"
            form="imprint-terms-form"
            :disabled="form.processing"
          >
            {{ $t('common.save') }}
          </Button>
        </PageHead>

        <div class="px-6 pt-7 pb-8">
          <div class="flex flex-wrap items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="imprint-terms" />

            <form id="imprint-terms-form" class="w-[620px] max-w-full min-w-0 flex-none" novalidate @submit.prevent="submit">
              <LegalTextFields :form="form" :families="settings.legalFamilies" :legal-templates="legalTemplates" />
            </form>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
