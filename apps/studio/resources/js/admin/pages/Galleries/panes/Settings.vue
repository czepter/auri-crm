<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { DatePicker } from '@/ui/date-picker'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
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
    balanceSettled: boolean
    requiresSettledBalance: boolean
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
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'
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
        class="flex items-center gap-2 rounded-lg px-3 py-2 text-[12.5px] whitespace-nowrap no-underline"
        :class="settingsSection === name ? 'bg-sunk-2 font-semibold text-foreground' : 'text-foreground'"
        :href="`/galerien/${gallery.id}/einstellungen/${name}`"
      >
        {{ $t(`galleryRecord.settingsNav.${name}`) }}
        <span
          v-if="pill[name] !== null"
          class="ml-auto rounded-[var(--r-sm)] px-1.5 py-[2px] text-[9.5px] leading-[1.5] font-semibold tracking-[0.04em] uppercase"
          :class="pill[name] ? 'bg-ok-bg text-ok' : 'bg-sunk-2 text-faint'"
        >{{ pill[name] ? $t('galleryPresets.on') : $t('galleryPresets.off') }}</span>
      </Link>
    </nav>

    <div class="min-w-0 flex-1">
  <!-- balance_settled is a state out of the payments, never a switch —
       it stands as a value beside the block it causes. -->
  <p v-if="gallery.requiresSettledBalance && !gallery.balanceSettled" class="mb-5 text-[11.5px] leading-[1.45] text-faint">
    {{ $t('galleryRecord.blockedByBalance') }}
  </p>

  <template v-if="settingsSection === 'allgemein'">
    <h2 class="mt-0">{{ $t('galleryRecord.settingsNav.allgemein') }}</h2>

    <div class="mb-5 flex flex-col gap-2 pb-5 shadow-[inset_0_-1px_0_var(--rule)]">
      <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.slugLabel') }}</div>
      <div class="flex items-center gap-1.5">
        <span class="text-[13px] whitespace-nowrap text-ink-2">{{ addressPrefixText }}</span>
        <Input v-model="general.slug" class="w-[220px]" type="text" maxlength="60" @blur="saveGeneral" />
      </div>
      <p v-if="general.errors.slug" class="text-[11.5px] leading-[1.45] text-destructive">{{ general.errors.slug }}</p>
      <!-- The one fact nobody could derive from a text field: changing
           the address does not break a link already in an inbox. -->
      <p class="text-[11.5px] leading-[1.45] text-faint">{{ $t('galleryRecord.slugHint') }}</p>
    </div>

    <div class="mb-5 flex items-start gap-[26px] pb-5 shadow-[inset_0_-1px_0_var(--rule)]">
      <div v-if="gallery.eventDate" class="flex min-w-0 flex-col gap-2">
        <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.eventDate') }}</div>
        <div class="mt-[5px] text-[15px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ day(gallery.eventDate) }}</div>
      </div>

      <div class="flex min-w-0 flex-col gap-2">
        <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.expiry') }}</div>
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
      <div v-for="version in versions" :key="version.version" class="flex items-baseline gap-[9px] py-2 shadow-[inset_0_1px_0_#EEEEF0] first:shadow-none">
        <span class="w-[62px] flex-none text-[12px] font-semibold tracking-[-0.01em] tabular-nums">{{ $t('galleryRecord.version', { version: version.version }) }}</span>
        <span class="text-[13px] font-medium tracking-[-0.01em]">{{ $t('contacts.assetCount', version.assetCount) }}</span>
        <span class="ml-auto text-[11.5px] whitespace-nowrap text-ink-2">{{ day(version.publishedAt) }}</span>
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
      <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('gallery.gateDownload') }}</div>
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
      <div v-for="list in lists" :key="list.id" class="flex items-baseline gap-[9px] py-2 shadow-[inset_0_1px_0_#EEEEF0] first:shadow-none">
        <span class="text-[13px] font-medium tracking-[-0.01em]">{{ list.name ?? $t('gallery.heart') }}</span>
        <span class="w-14 flex-none text-[10.5px] tracking-[0.05em] uppercase text-ink-2">{{ list.owner ?? $t('galleryRecord.guest') }}</span>
        <span class="ml-auto text-[11.5px] whitespace-nowrap text-ink-2">{{ $t('contacts.assetCount', list.count) }}</span>
      </div>
      <p v-if="!lists.length" class="text-[11.5px] leading-[1.45] text-faint">{{ $t('gallery.listEmpty') }}</p>
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
