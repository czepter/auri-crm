<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import ProfileForm from '@/admin/pages/Settings/panes/ProfileForm.vue'
import VatForm from '@/admin/pages/Settings/panes/VatForm.vue'
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { Button } from '@/ui/button'

defineProps<{
  settings: App.Settings.Data.StudioSettingsData
}>()

// Every other studio setting ships with a column default (see the
// studio_settings migrations), so a step confirming one asks for a decision
// before the studio has the context to make it. vat_mode is the exception:
// it is the one default that can be actively wrong, and it decides which
// invoice starter the wizard writes on finish.
const steps = ['profile', 'vat'] as const
const titles: Record<(typeof steps)[number], string> = {
  profile: 'onboarding.stepProfile',
  vat: 'onboarding.stepVat',
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
  <AuthLayout :title="$t(titles[step()])" width="max-w-[640px]">
    <p class="mb-6 text-[11.5px] leading-[1.45] font-normal text-faint">
      {{ $t('onboarding.stepCount', { current: stepIndex + 1, total: steps.length }) }}
    </p>

    <ProfileForm v-if="step() === 'profile'" ref="paneRef" :settings="settings" first-run @saved="advance" />
    <VatForm v-else ref="paneRef" :settings="settings" @saved="finish" />

    <div class="mt-6 flex gap-2.5">
      <Button
        type="button"
        class="flex-1 justify-center"
        :disabled="paneRef?.form.processing"
        @click="saveAndContinue"
      >
        {{ stepIndex === steps.length - 1 ? $t('onboarding.finish') : $t('onboarding.next') }}
      </Button>
      <Button variant="ghost" type="button" @click="finish">
        {{ $t('onboarding.skip') }}
      </Button>
    </div>
  </AuthLayout>
</template>
