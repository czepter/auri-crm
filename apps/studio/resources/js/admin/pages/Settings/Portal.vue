<script setup lang="ts">
import { computed, ref } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import SettingsNav from '@/admin/components/settings/SettingsNav.vue'
import PortalForm from './panes/PortalForm.vue'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { useI18n } from 'vue-i18n'
import { onKeyStroke, refDebounced } from '@vueuse/core'
import { Eye, Maximize2, Monitor, Smartphone, X } from 'lucide-vue-next'
import { useLiveProps } from '@/admin/lib/liveReload'
import { saveState } from '@/admin/lib/autosave'

defineProps<{
  settings: App.Settings.Data.PortalSettingsData
  branding: App.Settings.Data.StudioSettingsData
}>()
useLiveProps(['settings', 'branding'])

const { t } = useI18n()

const formRef = ref<InstanceType<typeof PortalForm>>()

// Welcher der drei Begrüßungstexte in der Vorschau steht — dieselben
// Zustände, die HomeController aus den Buchungen ableitet.
const state = ref<'inquiry' | 'active' | 'done'>('inquiry')
const register = ref<'formal' | 'informal'>('formal')

// Die Vorschau ist die Portal-Startseite selbst, in einem iframe: dieselben
// Bauteile, die der Kunde sieht. Was getippt wird, reist als Query mit, damit
// sie mitläuft, ohne dass gespeichert sein muss — entprellt, weil jede
// Änderung den Rahmen neu lädt.
const source = computed(() => {
  const form = formRef.value?.form
  const query = new URLSearchParams({ zustand: state.value, anrede: register.value })

  if (form) {
    query.set('text', form[`portal_welcome_${state.value}`])
    query.set('leistungen', form.portal_offers_enabled ? '1' : '0')
    query.set('geschlossen', form.portal_maintenance_enabled ? '1' : '0')
    query.set('hinweis', form.portal_maintenance_message)
  }

  return `/einstellungen/portal/vorschau?${query}`
})

const src = refDebounced(source, 400)

const stateOptions = ['inquiry', 'active', 'done'].map((value) => ({
  value,
  label: t(`portalSettings.welcomeState.${value}`),
}))

// Die Seite wird in der Breite gerendert, die sie bekommt — kein Maßstab,
// keine Lupe. Am Telefon sind das 390px, am Schreibtisch die Spalte selbst,
// und wem die zu schmal ist, der klappt die Vorschau auf: dann stehen die
// 1180px da, mit denen das Portal seine zwei Spalten aufmacht.
const DEVICES = {
  desktop: { width: null as number | null, expanded: 1180, icon: Monitor },
  mobile: { width: 390, expanded: 390, icon: Smartphone },
}

const device = ref<keyof typeof DEVICES>('mobile')

const registerOptions = (['formal', 'informal'] as const).map((value) => ({
  value,
  label: t(value === 'formal' ? 'settings.clientRegisterFormal' : 'settings.clientRegisterInformal'),
}))

const deviceOptions = (Object.keys(DEVICES) as (keyof typeof DEVICES)[]).map((value) => ({
  value,
  label: t(`portalSettings.device.${value}`),
  icon: DEVICES[value].icon,
}))

const expanded = ref(false)

onKeyStroke('Escape', () => (expanded.value = false))

const width = computed(() => {
  const size = expanded.value ? DEVICES[device.value].expanded : DEVICES[device.value].width

  return size === null ? '100%' : `${size}px`
})

</script>

<template>
  <AdminLayout active="einstellungen">
    <FormShell :kicker="$t('settings.kicker')" kicker-href="/einstellungen/allgemein" :title="$t('portalSettings.title')" :state="saveState([formRef?.form])">
      <template #head>
        <Button
          v-if="formRef?.form.isDirty"
          variant="pill"
          type="submit"
          form="portal-settings-form"
          :disabled="formRef?.form.processing"
        >
          {{ $t('common.save') }}
        </Button>
      </template>

      <template #nav><SettingsNav active="portal" /></template>

      <PortalForm ref="formRef" :settings="settings" />

      <template #preview>
            <aside class="sticky top-6 w-[420px] flex-none max-[1440px]:hidden">
              <div class="mb-2.5 flex flex-wrap items-center gap-2">
                <span class="mr-auto text-small leading-none font-semibold text-faint">{{ $t('settings.previewPortal') }}</span>
                <ChoiceGroup v-model="register" :label="$t('settings.clientRegister')" variant="segmented" :options="registerOptions" />
                <ChoiceGroup v-model="state" variant="segmented" :options="stateOptions" />
                <ChoiceGroup v-model="device" icon variant="segmented" :options="deviceOptions">
                  <template #option="{ option }">
                    <component :is="deviceOptions.find((row) => row.value === option.value)!.icon" />
                  </template>
                </ChoiceGroup>
                <Button
                  :aria-label="$t('portalSettings.previewExpand')"
                  class="size-[30px] justify-center px-0"
                  type="button"
                  variant="outline"
                  @click="expanded = true"
                >
                  <Maximize2 />
                </Button>
              </div>
              <div class="rounded-lg bg-muted p-3">
                <iframe
                  :src="src"
                  class="mx-auto block h-[760px] rounded-md border-0 bg-background"
                  :style="{ width }"
                  title=""
                />
              </div>
            </aside>

            <Button
              class="fixed right-6 bottom-6 z-40 shadow-[0_8px_24px_rgb(20_16_14_/_0.16)] min-[1441px]:hidden"
              variant="pill"
              type="button"
              @click="expanded = true"
            >
              <Eye />
              {{ $t('settings.previewShow') }}
            </Button>

            <!-- Aufgeklappt, weil 1180px neben einem Formular nirgends
                 hinpassen und das Portal erst dort seine zweite Spalte
                 aufmacht. -->
            <div v-if="expanded" class="fixed inset-0 z-50 overflow-y-auto bg-background/95 p-6 backdrop-blur-[12px]">
              <div class="mx-auto flex max-w-[1180px] flex-wrap items-center gap-2 pb-3">
                <ChoiceGroup v-model="state" variant="segmented" :options="stateOptions" />
                <ChoiceGroup v-model="device" icon variant="segmented" :options="deviceOptions">
                  <template #option="{ option }">
                    <component :is="deviceOptions.find((row) => row.value === option.value)!.icon" />
                  </template>
                </ChoiceGroup>
                <Button
                  :aria-label="$t('common.close')"
                  class="ml-auto size-[30px] justify-center px-0"
                  type="button"
                  variant="outline"
                  @click="expanded = false"
                >
                  <X />
                </Button>
              </div>
              <iframe
                :src="src"
                class="mx-auto block h-[calc(100dvh-140px)] rounded-lg border-0 bg-background shadow-[0_1px_2px_rgba(0,0,0,0.08)]"
                :style="{ width }"
                title=""
              />
            </div>
      </template>
    </FormShell>
  </AdminLayout>
</template>
