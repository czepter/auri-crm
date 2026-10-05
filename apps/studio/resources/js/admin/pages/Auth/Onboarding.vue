<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import EmailVerificationForm from '@/admin/pages/Auth/panes/EmailVerificationForm.vue'
import ProfileForm from '@/admin/pages/Settings/panes/ProfileForm.vue'
import { Button } from '@/ui/button'

const props = defineProps<{
  settings: App.Settings.Data.StudioSettingsData
  emailVerified: boolean
  hasLiveVerification: boolean
}>()

// One step, and it asks for what the client sees first: PortalHeader draws
// the display name, the logo and the accent, and the portal goes live with
// the first quote. Firmierung and Anschrift are due at that quote (they
// carry the Impressum), Steuernummer and bank at the first invoice — see
// the gates in Settings/panes.
const paneRef = ref<{ form: { processing: boolean; display_name: string; accent_color: string }; submit: () => void }>()

const preview = computed(() => ({
  name: paneRef.value?.form.display_name || props.settings.displayName || '',
  accent: paneRef.value?.form.accent_color || props.settings.accentColor || 'var(--accent)',
}))

function finish() {
  router.post('/einrichtung/studio/abschluss')
}
</script>

<template>
  <!-- Die Karte steht mittig und wächst mit dem Formular, statt den Bildschirm
       zu füllen: fünf Felder über 1400px gezogen lesen sich als leere Seite
       mit Rand. -->
  <div data-tokens class="flex min-h-screen items-center justify-center p-6">
    <div class="w-full max-w-[940px]">
      <div class="mb-3.5 flex items-center justify-between">
        <span class="text-title leading-none font-bold tracking-[-0.035em] text-foreground">{{ $page.props.brand }}</span>
        <Button variant="ghost" type="button" @click="finish">{{ $t('onboarding.skip') }}</Button>
      </div>

      <div class="flex overflow-hidden rounded-md border border-border bg-background max-lg:flex-col">
        <div class="flex w-[320px] flex-none flex-col gap-7 border-r border-border bg-sunk p-7 max-lg:w-full max-lg:border-r-0 max-lg:border-b">
          <div>
            <h1 class="text-[32px] leading-[var(--display-leading)] font-bold tracking-[var(--display-tracking)] text-foreground">
              {{ $t('onboarding.title') }}
            </h1>
            <p class="mt-3 text-small leading-[1.5] text-muted-foreground">{{ $t('onboarding.subtitle') }}</p>
          </div>

          <!-- Was der Kunde zuerst sieht, während es eingetragen wird — dieselben
               drei Werte, die PortalHeader zeichnet. -->
          <div class="overflow-hidden rounded-[12px] border border-border bg-background">
            <div class="flex items-center gap-2.5 border-b border-border px-3.5 py-3">
              <img v-if="settings.logoUrl" :src="settings.logoUrl" alt="" class="size-[24px] rounded-[6px] object-contain" />
              <span
                v-else
                class="flex size-[24px] items-center justify-center rounded-[6px] text-small font-bold text-paper"
                :style="{ background: preview.accent }"
              >{{ preview.name.slice(0, 1) }}</span>
              <span class="text-ui leading-none font-semibold tracking-[-0.02em] text-foreground">{{ preview.name }}</span>
            </div>
            <div class="px-3.5 py-3">
              <span class="inline-block rounded-[var(--r-pill)] bg-foreground px-4 py-2 text-small leading-none font-semibold text-paper">{{ $t('portal.quoteAccept') }}</span>
            </div>
          </div>
        </div>

        <div class="flex flex-grow flex-col gap-5 p-7">
          <EmailVerificationForm v-if="!emailVerified && hasLiveVerification" />
          <ProfileForm ref="paneRef" :settings="settings" first-run @saved="finish" />

          <div class="flex justify-end">
            <Button type="button" :disabled="paneRef?.form.processing" @click="paneRef?.submit()">
              {{ $t('onboarding.finish') }}
            </Button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
