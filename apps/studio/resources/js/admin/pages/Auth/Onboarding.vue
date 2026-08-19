<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import ProfileForm from '@/admin/pages/Settings/panes/ProfileForm.vue'
import VatForm from '@/admin/pages/Settings/panes/VatForm.vue'
import DocumentsForm from '@/admin/pages/Settings/panes/DocumentsForm.vue'
import EmailForm from '@/admin/pages/Settings/panes/EmailForm.vue'
import PortalForm from '@/admin/pages/Settings/panes/PortalForm.vue'
import StarterTemplatesForm from '@/admin/pages/Settings/panes/StarterTemplatesForm.vue'
import { Button } from '@/ui/button'

defineProps<{
  settings: App.Settings.Data.StudioSettingsData
  portalSettings: App.Settings.Data.PortalSettingsData
  starters: Record<string, { key: string; name: string; description: string; shootKind: string | null }[]>
  starterDefaults: string[]
  typeLabels: Record<string, string>
}>()

// T-18 sits after `documents`: the settings that step collects — validity,
// VAT mode, the Widerruf switch — are what the starters' merge fields read,
// so choosing templates before them would be choosing blind.
const steps = ['profile', 'vat', 'documents', 'templates', 'email', 'portal'] as const
const titles: Record<(typeof steps)[number], string> = {
  profile: 'onboarding.stepProfile',
  vat: 'onboarding.stepVat',
  documents: 'onboarding.stepDocuments',
  templates: 'onboarding.stepTemplates',
  email: 'onboarding.stepEmail',
  portal: 'onboarding.stepPortal',
}

const stepIndex = ref(0)
const step = () => steps[stepIndex.value]

// Only one of these is mounted at a time (v-if chain below), so only one
// ref is ever non-null. Each exposes { form, submit } — see
// Settings/panes/ProfileForm.vue and its siblings.
//
// stepIndex above survives each pane's own save (POST/PATCH -> redirect ->
// redirect -> back to this same Inertia component) only because
// router.post/patch default to preserveState: true, which keeps this Vue
// component instance — and therefore stepIndex — alive across that
// redirect chain. If a pane's redirect target or preserveState option ever
// changes, the wizard will silently reset to step 1 on every save.
const paneRef = ref<{ form: { processing: boolean }; submit: () => void }>()

function advance() {
  if (stepIndex.value < steps.length - 1) {
    stepIndex.value += 1
  } else {
    finish()
  }
}

function saveAndContinue() {
  paneRef.value?.submit()
}

function finish() {
  router.post('/einrichtung/studio/abschluss')
}
</script>

<template>
  <div class="authwrap">
    <div class="authcard" style="max-width: 640px">
      <span class="brand">{{ $page.props.brand }}</span>
      <h1>{{ $t(titles[step()]) }}</h1>
      <p class="hint" style="margin-bottom: var(--s5)">
        {{ $t('onboarding.stepCount', { current: stepIndex + 1, total: steps.length }) }}
      </p>

      <ProfileForm v-if="step() === 'profile'" ref="paneRef" :settings="settings" @saved="advance" />
      <VatForm v-else-if="step() === 'vat'" ref="paneRef" :settings="settings" @saved="advance" />
      <DocumentsForm v-else-if="step() === 'documents'" ref="paneRef" :settings="settings" @saved="advance" />
      <StarterTemplatesForm
        v-else-if="step() === 'templates'"
        ref="paneRef"
        :starters="starters"
        :defaults="starterDefaults"
        :type-labels="typeLabels"
        @saved="advance"
      />
      <EmailForm v-else-if="step() === 'email'" ref="paneRef" :settings="settings" @saved="advance" />
      <PortalForm v-else ref="paneRef" :settings="portalSettings" @saved="finish" />

      <div style="display: flex; gap: var(--s3); margin-top: var(--s5)">
        <Button
          type="button"
          style="flex: 1; justify-content: center"
          :disabled="paneRef?.form.processing"
          @click="saveAndContinue"
        >
          {{ stepIndex === steps.length - 1 ? $t('onboarding.finish') : $t('onboarding.next') }}
        </Button>
        <Button variant="ghost" type="button" @click="finish">
          {{ $t('onboarding.skip') }}
        </Button>
      </div>
    </div>
  </div>
</template>
