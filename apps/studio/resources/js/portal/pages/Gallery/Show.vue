<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

type Branding = { displayName: string | null }

const props = defineProps<{
  gallery: { id: string; title: string | null; date: string | null; venue: string | null }
  unlocked: boolean
}>()

// T-08's shared branding, the same wordmark PortalFooter reads — a gallery
// guest sees the studio's name, never the internal project it belongs to.
const studioName = computed(() => (usePage().props.portalBranding as Branding | undefined)?.displayName)

const form = useForm({ password: '' })

function submit() {
  form.post(`/galerien/${props.gallery.id}/passwort`)
}

const subtitle = computed(() => [props.gallery.date, props.gallery.venue].filter(Boolean).join(' · '))
</script>

<template>
  <!-- data-theme lives on an ancestor, never on .gpage itself — gallery.css's
       rules are all `[data-theme="gallery"] .foo` descendant selectors,
       matching the mockup's own <html data-theme="gallery"> root. -->
  <div data-theme="gallery">
    <div class="gpage gcenter">
      <span class="gmark">{{ studioName }}</span>

      <template v-if="unlocked">
        <h1 class="gtitle">{{ gallery.title }}</h1>
        <p class="gsub">{{ $t('gallery.unlockedHint') }}</p>
        <Button as="a" class="wide" :href="`/galerien/${gallery.id}/download`">{{ $t('gallery.downloadLink') }}</Button>
      </template>

      <template v-else>
        <h1 class="gtitle">{{ gallery.title }}</h1>
        <p v-if="subtitle" class="gsub">{{ subtitle }}</p>

        <form @submit.prevent="submit">
          <Label class="field">
            <span>{{ $t('gallery.passwordLabel') }}</span>
            <Input v-model="form.password" type="password" autocomplete="off" required />
          </Label>

          <Button class="wide" type="submit" :disabled="form.processing">
            {{ $t('gallery.passwordOpen') }}
          </Button>
        </form>

        <div v-if="form.errors.password" class="err">
          <b>{{ form.errors.password }}</b>
        </div>
      </template>
    </div>
  </div>
</template>
