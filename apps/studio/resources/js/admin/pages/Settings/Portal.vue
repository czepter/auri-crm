<script setup lang="ts">
import { computed, ref } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import PortalForm from './panes/PortalForm.vue'
import PreviewFrame from './previews/PreviewFrame.vue'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { useI18n } from 'vue-i18n'
import { refDebounced } from '@vueuse/core'

defineProps<{
  settings: App.Settings.Data.PortalSettingsData
  branding: App.Settings.Data.StudioSettingsData
}>()

const { t } = useI18n()

const formRef = ref<InstanceType<typeof PortalForm>>()

// Welcher der drei Begrüßungstexte in der Vorschau steht — dieselben
// Zustände, die HomeController aus den Buchungen ableitet.
const state = ref<'inquiry' | 'active' | 'done'>('inquiry')

// Die Vorschau ist die Portal-Startseite selbst, in einem iframe: dieselben
// Bauteile, die der Kunde sieht. Was getippt wird, reist als Query mit, damit
// sie mitläuft, ohne dass gespeichert sein muss — entprellt, weil jede
// Änderung den Rahmen neu lädt.
const source = computed(() => {
  const form = formRef.value?.form
  const query = new URLSearchParams({ zustand: state.value })

  if (form) {
    query.set('text', form[`portal_welcome_${state.value}`])
    query.set('geschlossen', form.portal_maintenance_enabled ? '1' : '0')
    query.set('hinweis', form.portal_maintenance_message)
    query.set('kontakt', [form.portal_contact_phone, form.portal_contact_email, form.portal_contact_response_time]
      .filter(Boolean).join(' · '))
  }

  return `/einstellungen/portal/vorschau?${query}`
})

const src = refDebounced(source, 400)

const stateOptions = ['inquiry', 'active', 'done'].map((value) => ({
  value,
  label: t(`portalSettings.welcomeState.${value}`),
}))
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

            <PreviewFrame class="w-[1180px]" :label="$t('settings.previewPortal')">
              <ChoiceGroup v-model="state" class="mb-4" variant="segmented" :options="stateOptions" />
              <!-- Die echte Seite, in ihrer eigenen Breite: das Portal misst
                   1180px, und darunter fällt es in sein Handy-Layout. -->
              <iframe :src="src" class="h-[900px] w-full rounded-md border-0 bg-background" title="" />
            </PreviewFrame>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
