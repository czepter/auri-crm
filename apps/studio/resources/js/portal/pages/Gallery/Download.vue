<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

type Branding = { displayName: string | null }

const props = defineProps<{
  gallery: { id: string; title: string | null }
  step: 'pin' | 'email' | 'ready'
}>()

const studioName = computed(() => (usePage().props.portalBranding as Branding | undefined)?.displayName)
const status = computed(() => usePage().props.flash.status)

const pinForm = useForm({ pin: '' })
const emailForm = useForm({ email: '' })

function submitPin() {
  pinForm.post(`/galerien/${props.gallery.id}/download/pin`)
}

function submitEmail() {
  emailForm.post(`/galerien/${props.gallery.id}/download/email`, { onSuccess: () => emailForm.reset() })
}
</script>

<template>
  <!-- data-theme lives on an ancestor, never on .gpage itself — gallery.css's
       rules are all `[data-theme="gallery"] .foo` descendant selectors,
       matching the mockup's own <html data-theme="gallery"> root. -->
  <div data-theme="gallery">
    <!-- mockup 11: the whole page centers on the pin step -->
    <div v-if="step === 'pin'" class="gpage wide gcenter">
      <span class="gmark">{{ studioName }}</span>
      <div class="kick" style="margin-top: 56px">{{ $t('gallery.pinKicker') }}</div>
      <h1 class="gtitle">{{ $t('gallery.pinTitle') }}</h1>

      <form @submit.prevent="submitPin">
        <label class="field">
          <span>{{ $t('gallery.pinLabel') }}</span>
          <input v-model="pinForm.pin" class="pin" type="text" inputmode="numeric" autocomplete="off" required />
        </label>

        <button class="btn wide" type="submit" :disabled="pinForm.processing">
          {{ $t('gallery.pinContinue') }}
        </button>
      </form>

      <div v-if="pinForm.errors.pin" class="err"><b>{{ pinForm.errors.pin }}</b></div>

      <a class="txtbtn mid" :href="`/galerien/${gallery.id}`">{{ $t('gallery.backToGallery') }}</a>
    </div>

    <!-- mockup 12: only the header centers, the field and actions stay left -->
    <div v-else-if="step === 'email'" class="gpage wide">
      <div class="gcenter">
        <span class="gmark">{{ studioName }}</span>
        <div class="kick" style="margin-top: 56px">{{ $t('gallery.emailKicker') }}</div>
        <h1 class="gtitle">{{ $t('gallery.emailTitle') }}</h1>
        <p v-if="gallery.title" class="gsub">{{ gallery.title }}</p>
      </div>

      <p v-if="status" class="hint">{{ status }}</p>

      <form v-else @submit.prevent="submitEmail">
        <label class="field">
          <span>{{ $t('gallery.emailLabel') }}</span>
          <input v-model="emailForm.email" type="email" autocomplete="email" required />
        </label>

        <button class="btn wide" type="submit" :disabled="emailForm.processing">
          {{ $t('gallery.emailRequest') }}
        </button>
      </form>

      <a class="txtbtn mid" :href="`/galerien/${gallery.id}`">{{ $t('gallery.backToGallery') }}</a>
    </div>

    <!-- no mockup names this state (both gates cleared) — the actual delivery
         is G-10/G-11's, so this is a minimal placeholder rather than a guess
         at a screen those stories have not designed yet. -->
    <div v-else class="gpage wide gcenter">
      <span class="gmark">{{ studioName }}</span>
      <h1 class="gtitle">{{ $t('gallery.readyTitle') }}</h1>
      <p class="gsub">{{ $t('gallery.readyHint') }}</p>
      <a class="txtbtn mid" :href="`/galerien/${gallery.id}`">{{ $t('gallery.backToGallery') }}</a>
    </div>
  </div>
</template>
