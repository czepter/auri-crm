<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router, useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { CopyButton } from '@/ui/copy-button'
import { DatePicker } from '@/ui/date-picker'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { Link } from '@inertiajs/vue3'
import Chip from '@/ui/page/Chip.vue'
import { Checkbox } from '@/ui/checkbox'
import GalleryAccess from '@/admin/components/gallery/GalleryAccess.vue'
import GalleryFold from '@/admin/components/gallery/GalleryFold.vue'
import { addressPrefix } from '@/admin/lib/galleryAddress'
import { decideExpiryOnBlur } from '@/admin/lib/galleryExpiry'
import GalleryReleaseFields, { type ReleasePatch, type ReleaseValues } from '@/admin/components/gallery/GalleryReleaseFields.vue'
import { ACTIONS, AUDIENCES, type Audience, type Permissions } from '@/admin/lib/galleryPermissions'
import { Switch } from '@/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { shortDate } from '@/admin/lib/dates'

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
    permissions: Permissions
    introEnabled: boolean
    studioName: string | null
    viewMode: 'none' | 'text' | 'logo' | 'locked'
    watermarkText: string | null
    downloadRule: 'open' | 'after_payment' | 'off'
    downloadInvoiceId: string | null
    downloadSizes: ('original' | 'web')[]
    expiryReminder: boolean
    invoices: { id: string; number: string; status: string }[]
    followsPlan: boolean
    listedOnHome: boolean
    homePinned: boolean
    homeBlocker: 'locked' | 'draft' | 'withdrawn' | 'expired' | 'external' | null
    homeMode: 'none' | 'redirect' | 'overview'
    categoryId: string | null
    categories: { id: string; name: string }[]
  }
  siblings: { id: string; title: string | null; assetCount: number; open: boolean }[]
  settingsSection: 'zugang' | 'download' | 'darstellung'
}>()

const { t } = useI18n()

const SECTIONS = ['zugang', 'download', 'darstellung'] as const

// Der Download ist der eine Schalter unter den Abschnitten — sein Zustand
// steht in der Zeile, die zu ihm führt, statt erst dahinter.
const pill = computed<Record<string, boolean | null>>(() => ({
  zugang: null,
  download: props.gallery.downloadRule !== 'off',
  darstellung: null,
}))

// The address and the expiry, the two facts a studio actually
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

const NO_CATEGORY = 'none'

const category = useForm({ project_type_id: props.gallery.categoryId ?? NO_CATEGORY })

function saveCategory() {
  category.transform((data) => ({ project_type_id: data.project_type_id === NO_CATEGORY ? null : data.project_type_id }))
    .patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const home = useForm({ listed_on_home: props.gallery.listedOnHome, home_pinned: props.gallery.homePinned })

function saveHome() {
  home.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const addressPrefixText = computed(() => addressPrefix(props.gallery.url))

const SLUG_FIELD = 'gallery-slug'

// Download: the PIN belongs beside the switch that offers it, not in Zugang.
const downloadPin = useForm({ download_pin: props.gallery.downloadPin ?? '' })

function saveDownloadPin() {
  downloadPin.transform((data) => ({ download_pin: data.download_pin === '' ? null : data.download_pin }))
    .patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const view = useForm({ view_mode: props.gallery.viewMode, watermark_text: props.gallery.watermarkText })

const download = useForm({
  download_rule: props.gallery.downloadRule,
  download_invoice_id: props.gallery.downloadInvoiceId,
  download_sizes: props.gallery.downloadSizes,
})

const release = useForm({ permissions: props.gallery.permissions, intro_enabled: props.gallery.introEnabled })

const releaseValues = computed<ReleaseValues>(() => ({
  view_mode: view.view_mode,
  watermark_text: view.watermark_text,
  download_rule: download.download_rule,
  download_sizes: download.download_sizes,
  permissions: release.permissions,
  intro_enabled: release.intro_enabled,
}))

function applyRelease(patch: ReleasePatch) {
  const { download_invoice_id: invoice, ...values } = patch
  Object.assign(view, pick(values, ['view_mode', 'watermark_text']))
  Object.assign(download, pick(values, ['download_rule', 'download_sizes']), invoice === undefined ? {} : { download_invoice_id: invoice })
  Object.assign(release, pick(values, ['permissions', 'intro_enabled']))

  router.patch(`/galerien/${props.gallery.id}`, { ...patch }, { preserveScroll: true })
}

function pick<T extends object, K extends keyof T>(source: T, keys: K[]): Partial<Pick<T, K>> {
  return Object.fromEntries(keys.filter((key) => key in source).map((key) => [key, source[key]])) as Partial<Pick<T, K>>
}

const reminder = useForm({ expiry_reminder: props.gallery.expiryReminder })

function setReminder(on: boolean) {
  reminder.expiry_reminder = on
  reminder.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const onOff = (value: boolean) => t(value ? 'galleryPresets.on' : 'galleryPresets.off')

const categoryName = computed(() => props.gallery.categories.find((row) => row.id === category.project_type_id)?.name ?? t('galleryRecord.categoryNone'))

const rights = computed(() => AUDIENCES.map((audience: Audience) => {
  const allowed = ACTIONS.filter((action) => release.permissions[audience][action]).map((action) => t(`galleryRecord.permission.${action}`))

  return `${t(`galleryRecord.permission.${audience}`)}: ${allowed.join(', ') || '—'}`
}).join(' · '))

function day(iso: string | null) {
  return iso ? shortDate(iso) : '—'
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
  <template v-if="settingsSection === 'zugang'">
    <GalleryAccess :gallery="gallery" :siblings="siblings" />

    <div v-if="gallery.isExternal" class="mt-5 flex max-w-[440px] flex-col gap-4">
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

    <div class="my-5 flex items-start gap-[26px]">
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
             address field below would PATCH that half-typed date's null
             straight through as this gallery's new expiry. -->
        <DatePicker :model-value="general.expires_at" class="w-[150px]" @blur="saveExpiryOnBlur" />
      </div>

      <div class="flex min-w-0 flex-col gap-2 self-end pb-1.5">
        <div class="flex items-center gap-2.5">
          <Switch id="sw-reminder" :model-value="reminder.expiry_reminder" @update:model-value="setReminder" />
          <Label variant="inline" for="sw-reminder">{{ $t('galleryRecord.expiryReminder') }}</Label>
        </div>
      </div>
    </div>

    <div class="mt-8 flex flex-col">
      <GalleryFold :label="$t('galleryRecord.slugLabel')" :value="general.slug" :error="!!general.errors.slug">
        <!-- Ein Feld, nicht zwei Dinge nebeneinander: der feste Teil der
             Adresse ist nichts zum Tippen, und kopiert wird die ganze. -->
        <InputGroup class="max-w-[440px]">
          <InputGroupAddon>{{ addressPrefixText }}</InputGroupAddon>
          <InputGroupInput :id="SLUG_FIELD" v-model="general.slug" type="text" maxlength="60" :aria-label="$t('galleryRecord.slugLabel')" @blur="saveGeneral" />
          <CopyButton :value="addressPrefixText + general.slug" variant="ghost" class="flex-none px-3" />
        </InputGroup>
        <p v-if="general.errors.slug" class="mt-2 text-small leading-[1.45] text-destructive">{{ general.errors.slug }}</p>
        <!-- The one fact nobody could derive from a text field: there is no
             slug history, so a link already in an inbox stops resolving. -->
        <p class="mt-2 text-small leading-[1.45] text-faint">{{ $t('galleryRecord.slugHint') }}</p>
      </GalleryFold>

      <GalleryFold v-if="!gallery.isExternal" :label="$t('galleryRecord.category')" :value="categoryName" :error="!!category.errors.project_type_id">
        <Select v-model="category.project_type_id" @update:model-value="saveCategory">
          <SelectTrigger id="gallery-category" class="w-[220px]" :aria-label="$t('galleryRecord.category')"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem :value="NO_CATEGORY">{{ $t('galleryRecord.categoryNone') }}</SelectItem>
            <SelectItem v-for="type in gallery.categories" :key="type.id" :value="type.id">{{ type.name }}</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="category.errors.project_type_id" class="mt-2 text-small leading-[1.45] text-destructive">{{ category.errors.project_type_id }}</p>
      </GalleryFold>

      <template v-if="!gallery.isExternal">
        <GalleryFold :label="$t('galleryRecord.home')" :value="onOff(home.listed_on_home)" :error="!!home.errors.listed_on_home">
          <div class="flex items-center gap-2.5">
            <Checkbox id="cb-home-listed" v-model="home.listed_on_home" @update:model-value="saveHome" />
            <Label variant="inline" for="cb-home-listed">{{ $t('galleryRecord.listedOnHome') }}</Label>
            <template v-if="home.listed_on_home">
              <Link
                v-if="gallery.homeMode !== 'overview'"
                class="text-small text-ink-2 no-underline hover:underline"
                href="/galerien?einstellungen=startseite"
              >{{ $t('galleryRecord.homeOff') }} →</Link>
              <Chip v-else-if="gallery.homeBlocker" tone="warn">
                {{ $t('galleryRecord.homeHidden', { reason: $t(`galleryRecord.homeBlocker.${gallery.homeBlocker}`) }) }}
              </Chip>
              <Chip v-else tone="success">{{ $t('galleryRecord.homeListed') }}</Chip>
            </template>
          </div>
          <p v-if="home.errors.listed_on_home" class="mt-2 text-small leading-[1.45] text-destructive">{{ home.errors.listed_on_home }}</p>
        </GalleryFold>

        <GalleryFold :label="$t('galleryRecord.homePinned')" :value="onOff(home.home_pinned)" :error="!!home.errors.home_pinned">
          <div class="flex items-center gap-2.5">
            <Checkbox id="cb-home-pinned" v-model="home.home_pinned" :disabled="!home.listed_on_home" @update:model-value="saveHome" />
            <Label variant="inline" for="cb-home-pinned">{{ $t('galleryRecord.homePinned') }}</Label>
          </div>
          <p v-if="home.errors.home_pinned" class="mt-2 text-small leading-[1.45] text-destructive">{{ home.errors.home_pinned }}</p>
        </GalleryFold>
      </template>

      <GalleryFold :label="$t('galleryRecord.rights')" :value="rights">
        <GalleryReleaseFields :model-value="releaseValues" :parts="['permissions']" @change="applyRelease" />
      </GalleryFold>
    </div>
  </template>

  <template v-else-if="settingsSection === 'download'">
    <h2 class="mt-0">{{ $t('galleryRecord.settingsNav.download') }}</h2>
    <GalleryReleaseFields
      :model-value="releaseValues"
      :parts="['download']"
      :invoices="gallery.invoices"
      :invoice-id="download.download_invoice_id"
      :follows-plan="gallery.followsPlan"
      @change="applyRelease"
    />
    <div class="flex flex-col gap-2">
      <div class="text-small text-ink-2">{{ $t('gallery.gateDownload') }}</div>
      <Input v-model="downloadPin.download_pin" class="w-[110px]" type="text" @blur="saveDownloadPin" />
    </div>
  </template>

  <template v-else-if="settingsSection === 'darstellung'">
    <h2 class="mt-0">{{ $t('galleryRecord.settingsNav.darstellung') }}</h2>
    <GalleryReleaseFields
      :model-value="releaseValues"
      :placeholder="gallery.studioName"
      :parts="['view', 'intro']"
      @change="applyRelease"
    />
  </template>
  </div>
  </div>
</template>
