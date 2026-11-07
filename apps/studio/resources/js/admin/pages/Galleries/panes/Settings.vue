<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Check, Copy } from 'lucide-vue-next'
import { DatePicker } from '@/ui/date-picker'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { Link } from '@inertiajs/vue3'
import GalleryAccess from '@/admin/components/gallery/GalleryAccess.vue'
import { addressPrefix } from '@/admin/lib/galleryAddress'
import { decideExpiryOnBlur } from '@/admin/lib/galleryExpiry'

const props = defineProps<{
  gallery: {
    id: string
    title: string | null
    slug: string
    url: string
    eventDate: string | null
    viewPassword: string | null
    downloadPin: string | null
    requiresVerifiedEmail: boolean
    expiresAt: string | null
    isExternal: boolean
    externalUrl: string | null
    externalPassword: string | null
    info: string | null
    offers: { downloads: boolean; lists: boolean; comments: boolean }
  }
  siblings: { id: string; title: string | null; assetCount: number; open: boolean }[]
  versions: { version: number; publishedAt: string | null; assetCount: number }[]
  lists: { id: string; name: string | null; owner: string | null; count: number }[]
  settingsSection: 'allgemein' | 'zugang' | 'download' | 'listen' | 'kommentare'
}>()

const SECTIONS = ['allgemein', 'zugang', 'download', 'listen', 'kommentare'] as const

// Drei der fünf Abschnitte sind Schalter — ihr Zustand steht in der Zeile,
// die zu ihnen führt, statt erst dahinter.
const pill = computed<Record<string, boolean | null>>(() => ({
  allgemein: null,
  zugang: null,
  download: props.gallery.offers.downloads,
  listen: props.gallery.offers.lists,
  kommentare: props.gallery.offers.comments,
}))

// Allgemein: the address and the expiry, the two facts a studio actually
// changes after a gallery already exists.
const general = useForm({ slug: props.gallery.slug, expires_at: props.gallery.expiresAt ?? '' })

const external = useForm({
  external_url: props.gallery.externalUrl ?? '',
  external_password: props.gallery.externalPassword ?? '',
  info: props.gallery.info ?? '',
})

function saveExternal() {
  external.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

function saveGeneral() {
  general.transform((data) => ({ ...data, expires_at: data.expires_at === '' ? null : data.expires_at }))
    .patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

// R30: save on the settled value DatePicker's own blur emit carries, never
// on update:modelValue — that fires every keystroke and cannot tell a
// deliberate clear from a half-typed date. decideExpiryOnBlur() is the
// three-way call; a false decision leaves general.expires_at untouched, so
// the next saveGeneral() (any other field's blur) can't smuggle a half-typed
// date through either.
function saveExpiryOnBlur(text: string) {
  const decision = decideExpiryOnBlur(text)
  if (!decision.save) return

  general.expires_at = decision.value ?? ''
  saveGeneral()
}

const addressPrefixText = computed(() => addressPrefix(props.gallery.url))

// Was auf dem Schirm steht, nicht was gespeichert ist: der Knopf nimmt dem
// Feld den Fokus, blur speichert, danach sind beide dasselbe.
const copied = ref(false)
// Über die id, nicht über einen Template-Ref: InputGroupInput ist eine
// Komponente, ein Ref darauf gibt die Instanz und select() liefe ins Leere.
const SLUG_FIELD = 'gallery-slug'

// Verweigert der Browser die Zwischenablage, bleibt der Knopf sonst stumm
// stehen und niemand merkt, dass nichts kopiert wurde. Dann markiert er
// stattdessen das Feld — von Hand kopieren geht immer.
function copyAddress() {
  copied.value = false

  const fallback = () => document.querySelector<HTMLInputElement>(`#${SLUG_FIELD}`)?.select()

  if (!navigator.clipboard) {
    fallback()

    return
  }

  navigator.clipboard
    .writeText(addressPrefixText.value + general.slug)
    .then(() => (copied.value = true))
    .catch(fallback)
}

// Download: the PIN belongs beside the switch that offers it, not in Zugang.
const downloadPin = useForm({ download_pin: props.gallery.downloadPin ?? '' })

function saveDownloadPin() {
  downloadPin.transform((data) => ({ download_pin: data.download_pin === '' ? null : data.download_pin }))
    .patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const offers = useForm({
  downloads_enabled: props.gallery.offers.downloads,
  lists_enabled: props.gallery.offers.lists,
  comments_enabled: props.gallery.offers.comments,
})

function saveOffers() {
  offers.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'
}
</script>

<template>
  <!-- Zwei Spalten, und beide tragen etwas: links die Abschnitte mit ihrem
       Zustand, rechts der eine, an dem gerade gearbeitet wird. Unter 900px
       legt sich die linke hin — drei Spalten nebeneinander gibt es dort
       nicht mehr. -->
  <div class="flex min-w-0 flex-1 gap-5 max-[900px]:flex-col">
    <nav class="flex w-[184px] flex-none flex-col gap-px max-[900px]:w-full max-[900px]:flex-row max-[900px]:overflow-x-auto">
      <Link
        v-for="name in SECTIONS"
        :key="name"
        class="flex items-center gap-2 rounded-lg px-3 py-2 text-small whitespace-nowrap no-underline"
        :class="settingsSection === name ? 'bg-sunk-2 font-semibold text-foreground' : 'text-foreground'"
        :href="`/galerien/${gallery.id}/einstellungen/${name}`"
      >
        {{ $t(`galleryRecord.settingsNav.${name}`) }}
        <span
          v-if="pill[name] !== null"
          class="ml-auto rounded-[var(--r-sm)] px-1.5 py-[2px] text-[9.5px] leading-[1.5] font-semibold"
          :class="pill[name] ? 'bg-ok-bg text-ok' : 'bg-sunk-2 text-faint'"
        >{{ pill[name] ? $t('galleryPresets.on') : $t('galleryPresets.off') }}</span>
      </Link>
    </nav>

    <div class="min-w-0 flex-1">
  <template v-if="settingsSection === 'allgemein'">
    <h2 class="mt-0">{{ $t('galleryRecord.settingsNav.allgemein') }}</h2>

    <div v-if="gallery.isExternal" class="mb-5 flex max-w-[440px] flex-col gap-4 pb-5 shadow-[inset_0_-1px_0_var(--rule)]">
      <div class="flex flex-col gap-1.5">
        <Label for="gallery-url">{{ $t('galleries.urlField') }}</Label>
        <Input id="gallery-url" v-model="external.external_url" type="url" @blur="saveExternal" />
        <p v-if="external.errors.external_url" class="text-[11.5px] leading-[1.45] text-destructive">{{ external.errors.external_url }}</p>
      </div>
      <div class="flex flex-col gap-1.5">
        <Label for="gallery-password">{{ $t('galleries.passwordField') }}</Label>
        <Input id="gallery-password" v-model="external.external_password" type="text" @blur="saveExternal" />
      </div>
      <div class="flex flex-col gap-1.5">
        <Label for="gallery-info">{{ $t('galleries.infoField') }}</Label>
        <Textarea id="gallery-info" v-model="external.info" rows="3" @blur="saveExternal" />
      </div>
    </div>

    <div class="mb-5 flex flex-col gap-2 pb-5 shadow-[inset_0_-1px_0_var(--rule)]">
      <div class="text-small text-ink-2">{{ $t('galleryRecord.slugLabel') }}</div>
      <!-- Ein Feld, nicht zwei Dinge nebeneinander: der feste Teil der
           Adresse ist nichts zum Tippen, und kopiert wird die ganze. -->
      <InputGroup class="max-w-[440px]">
        <InputGroupAddon>{{ addressPrefixText }}</InputGroupAddon>
        <InputGroupInput :id="SLUG_FIELD" v-model="general.slug" type="text" maxlength="60" :aria-label="$t('galleryRecord.slugLabel')" @blur="saveGeneral" />
        <button
          class="flex cursor-pointer items-center border-0 bg-transparent pr-[11px] pl-1.5 text-ink-2 hover:text-foreground [&_svg]:size-[15px]"
          type="button"
          :aria-label="$t('gallery.copy')"
          :title="$t('gallery.copy')"
          @click="copyAddress"
        >
          <Check v-if="copied" class="text-ok" />
          <Copy v-else />
        </button>
      </InputGroup>
      <p v-if="general.errors.slug" class="text-small leading-[1.45] text-destructive">{{ general.errors.slug }}</p>
      <!-- The one fact nobody could derive from a text field: there is no
           slug history, so a link already in an inbox stops resolving. -->
      <p class="text-small leading-[1.45] text-faint">{{ $t('galleryRecord.slugHint') }}</p>
    </div>

    <div class="mb-5 flex items-start gap-[26px] pb-5 shadow-[inset_0_-1px_0_var(--rule)]">
      <div v-if="gallery.eventDate" class="flex min-w-0 flex-col gap-2">
        <div class="text-small text-ink-2">{{ $t('galleryRecord.eventDate') }}</div>
        <div class="mt-[5px] text-body leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ day(gallery.eventDate) }}</div>
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <div class="text-small text-ink-2">{{ $t('galleryRecord.expiry') }}</div>
        <!-- One-way, not v-model: general.expires_at must hold only a
             settled value — see saveGeneral()'s own doc, and the note on
             saveExpiryOnBlur() above. update:modelValue would otherwise write
             every keystroke's partial parse into it, and a later blur on the
             address field above would PATCH that half-typed date's null
             straight through as this gallery's new expiry. -->
        <DatePicker :model-value="general.expires_at" class="w-[150px]" @blur="saveExpiryOnBlur" />
      </div>
    </div>

    <h2 class="mt-9">{{ $t('galleryRecord.versions') }}</h2>
    <div class="mb-9">
      <div v-for="version in versions" :key="version.version" class="flex items-baseline gap-[9px] py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none">
        <span class="w-[62px] flex-none text-small font-semibold tracking-[-0.01em] tabular-nums">{{ $t('galleryRecord.version', { version: version.version }) }}</span>
        <span class="text-small font-medium tracking-[-0.01em]">{{ $t('contacts.assetCount', version.assetCount) }}</span>
        <span class="ml-auto text-small whitespace-nowrap text-ink-2">{{ day(version.publishedAt) }}</span>
      </div>
    </div>
  </template>

  <GalleryAccess v-else-if="settingsSection === 'zugang'" :gallery="gallery" :siblings="siblings" />

  <template v-else-if="settingsSection === 'download'">
    <h2 class="mt-0">{{ $t('galleryRecord.settingsNav.download') }}</h2>
    <div class="mb-5 flex items-center gap-2.5">
      <Checkbox id="cb-offer-downloads" v-model="offers.downloads_enabled" @update:model-value="saveOffers" />
      <Label variant="inline" for="cb-offer-downloads">{{ $t('gallerySettings.downloadsLabel') }}</Label>
    </div>

    <div class="flex flex-col gap-2">
      <div class="text-small text-ink-2">{{ $t('gallery.gateDownload') }}</div>
      <Input v-model="downloadPin.download_pin" class="w-[110px]" type="text" @blur="saveDownloadPin" />
    </div>
  </template>

  <template v-else-if="settingsSection === 'listen'">
    <h2 class="mt-0">{{ $t('galleryRecord.settingsNav.listen') }}</h2>
    <div class="mb-5 flex items-center gap-2.5">
      <Checkbox id="cb-offer-lists" v-model="offers.lists_enabled" @update:model-value="saveOffers" />
      <Label variant="inline" for="cb-offer-lists">{{ $t('gallerySettings.listsLabel') }}</Label>
    </div>

    <div>
      <div v-for="list in lists" :key="list.id" class="flex items-baseline gap-[9px] py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none">
        <span class="text-small font-medium tracking-[-0.01em]">{{ list.name ?? $t('gallery.heart') }}</span>
        <span class="w-14 flex-none text-small text-ink-2">{{ list.owner ?? $t('galleryRecord.guest') }}</span>
        <span class="ml-auto text-small whitespace-nowrap text-ink-2">{{ $t('contacts.assetCount', list.count) }}</span>
      </div>
      <p v-if="!lists.length" class="text-small leading-[1.45] text-faint">{{ $t('gallery.listEmpty') }}</p>
    </div>
  </template>

  <template v-else>
    <h2 class="mt-0">{{ $t('galleryRecord.settingsNav.kommentare') }}</h2>
    <div class="flex items-center gap-2.5">
      <Checkbox id="cb-offer-comments" v-model="offers.comments_enabled" @update:model-value="saveOffers" />
      <Label variant="inline" for="cb-offer-comments">{{ $t('gallerySettings.commentsLabel') }}</Label>
    </div>
  </template>
  </div>
  </div>
</template>
