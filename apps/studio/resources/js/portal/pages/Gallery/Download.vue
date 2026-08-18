<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

type Branding = { displayName: string | null }

type Selection = { id: string; name: string | null; count: number; sizes: Record<string, number> }
type Part = { number: number; bytes: number; url: string }
type Build = {
  id: string
  list: string
  listName: string | null
  size: string
  status: 'building' | 'ready' | 'failed'
  partCount: number
  parts: Part[]
}

const props = defineProps<{
  gallery: { id: string; title: string | null; count: number; expiresAt: string | null }
  step: 'pin' | 'email' | 'ready'
  gates: { key: string; value: string | null }[] | null
  selections: Selection[]
  builds: Build[]
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

// G-03 (#231): what and how big. Both are choices before the archive exists,
// which is why the size sits on every line — nobody should start a
// four-gigabyte transfer to find out how big it was.
const chosenList = ref(props.selections[0]?.id ?? '')
const chosenSize = ref('original')

const selection = computed(() => props.selections.find((s) => s.id === chosenList.value) ?? null)

const build = computed(() =>
  props.builds.find((b) => b.list === chosenList.value && b.size === chosenSize.value) ?? null,
)

const prepareForm = useForm({})

function prepare() {
  prepareForm
    .transform(() => ({ list: chosenList.value || null, size: chosenSize.value }))
    .post(`/galerien/${props.gallery.id}/download/vorbereiten`, { preserveScroll: true })
}

// A build that is still going does not report progress — nothing measures
// it — so the screen re-asks rather than pretending to know how far along
// it is.
let poll: number | undefined
watch(
  () => props.builds.some((b) => b.status === 'building'),
  (building) => {
    window.clearInterval(poll)

    if (building) {
      poll = window.setInterval(() => router.reload({ only: ['builds'] }), 4000)
    }
  },
  { immediate: true },
)
onUnmounted(() => window.clearInterval(poll))

/**
 * Bytes as a studio's client would read them, not as a machine writes them.
 * Zero is a dash rather than "1 KB": a gallery whose assets never got a WebP
 * derivative has nothing at that size, and rounding it up to a plausible
 * number is the screen telling a lie it will be caught in.
 */
function size(bytes: number) {
  if (bytes <= 0) return '—'
  if (bytes >= 1_000_000_000) return `${(bytes / 1_000_000_000).toFixed(1).replace('.', ',')} GB`
  if (bytes >= 1_000_000) return `${Math.round(bytes / 1_000_000)} MB`

  return `${Math.max(1, Math.round(bytes / 1000))} KB`
}

/** Nothing to pack at this size means nothing to offer at it. */
function available(option: string) {
  return (selection.value?.sizes[option] ?? 0) > 0
}

function longDate(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric' })
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
        <Label class="field">
          <span>{{ $t('gallery.pinLabel') }}</span>
          <Input v-model="pinForm.pin" class="pin" type="text" inputmode="numeric" autocomplete="off" required />
        </Label>

        <Button class="wide" type="submit" :disabled="pinForm.processing">
          {{ $t('gallery.pinContinue') }}
        </Button>
      </form>

      <div v-if="pinForm.errors.pin" class="err"><b>{{ pinForm.errors.pin }}</b></div>

      <Button as="a" variant="ghost" class="mid" :href="`/galerien/${gallery.id}`">{{ $t('gallery.backToGallery') }}</Button>
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
        <Label class="field">
          <span>{{ $t('gallery.emailLabel') }}</span>
          <Input v-model="emailForm.email" type="email" autocomplete="email" required />
        </Label>

        <Button class="wide" type="submit" :disabled="emailForm.processing">
          {{ $t('gallery.emailRequest') }}
        </Button>
      </form>

      <Button as="a" variant="ghost" class="mid" :href="`/galerien/${gallery.id}`">{{ $t('gallery.backToGallery') }}</Button>
    </div>

    <!-- G-03 (#231), mockup galerie-download.html: both gates cleared, so
         the screen finally offers something instead of saying "Alles bereit"
         and stopping there. -->
    <div v-else class="dlcol">
      <a class="txtbtn" :href="`/galerien/${gallery.id}`">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6" /></svg>
        {{ $t('gallery.backToGallery') }}
      </a>

      <h1 class="gtitle" style="margin-top: var(--s5)">{{ $t('gallery.downloadTitle') }}</h1>
      <p class="gsub">
        {{ [gallery.title, $t('portal.imageCount', gallery.count)].filter(Boolean).join(' · ') }}
        <template v-if="gallery.expiresAt">
          · {{ $t('gallery.onlineUntil', { date: longDate(gallery.expiresAt) }) }}
        </template>
      </p>

      <!-- What was asked and answered, rather than forgotten the moment it
           passes. -->
      <div v-if="gates?.length" class="gates">
        <span v-for="gate in gates" :key="gate.key" class="gc">
          <svg viewBox="0 0 24 24"><path d="M4 12l6 6L20 6" /></svg>
          {{ gate.value ?? $t(`gallery.${gate.key}`) }}
        </span>
      </div>

      <section class="block">
        <p class="lab">{{ $t('gallery.whatLabel') }}</p>
        <div class="opts">
          <label v-for="option in selections" :key="option.id" class="opt">
            <input v-model="chosenList" type="radio" :value="option.id" />
            <span>
              <b>{{ option.name ?? (option.id ? $t('gallery.heart') : $t('gallery.everything')) }}</b>
              <span>{{ $t('portal.imageCount', option.count) }}</span>
            </span>
            <span class="dlsize">{{ size(option.sizes[chosenSize] ?? 0) }}</span>
          </label>
        </div>
      </section>

      <section class="block">
        <p class="lab">{{ $t('gallery.sizeLabel') }}</p>
        <div class="opts">
          <!-- ZipBuildSize::Web is "Zum Teilen" to a client; the enum value
               stays what it is. de.json wins over any class name. -->
          <label
            v-for="option in (['original', 'web'] as const)"
            :key="option"
            class="opt"
            :class="{ off: !available(option) }"
          >
            <input v-model="chosenSize" type="radio" :value="option" :disabled="!available(option)" />
            <span>
              <b>{{ $t(`gallery.size.${option}`) }}</b>
              <span>{{ $t(`gallery.sizeHint.${option}`) }}</span>
            </span>
            <span class="dlsize">{{ size(selection?.sizes[option] ?? 0) }}</span>
          </label>
        </div>

        <Button
          type="button"
          style="margin-top: var(--s5)"
          :disabled="prepareForm.processing || build?.status === 'building' || !available(chosenSize)"
          @click="prepare"
        >
          {{ build?.status === 'building' ? $t('gallery.preparing') : $t('gallery.prepare') }}
        </Button>
      </section>

      <!-- There are parts, not a file: BuildGalleryZip plans volumes, and one
           button would be lying about 4,2 GB. -->
      <section v-if="build" class="block">
        <p class="lab">{{ $t('gallery.yourFiles') }}</p>

        <p v-if="build.status === 'failed'" class="err"><b>{{ $t('gallery.buildFailed') }}</b></p>

        <p v-else-if="build.status === 'building'" class="hint">{{ $t('gallery.preparingHint') }}</p>

        <div v-else class="parts">
          <div v-for="part in build.parts" :key="part.number" class="part">
            <span>
              <b>{{ $t('gallery.partOf', { at: part.number, of: build.partCount }) }}</b>
            </span>
            <span class="dlsize">{{ size(part.bytes) }}</span>
            <Button as="a" variant="outline" :href="part.url">{{ $t('gallery.download') }}</Button>
          </div>
        </div>

        <p class="hint">{{ $t('gallery.partsLive') }}</p>
      </section>
    </div>
  </div>
</template>
