<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { ChoiceGroup } from '@/ui/choice-group'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import GalleryShareDialog from '@/admin/components/gallery/GalleryShareDialog.vue'
import GalleryNav from '@/admin/components/gallery/GalleryNav.vue'
import { publishLabelKey, showPublish as showPublishRule, showWithdraw as showWithdrawRule, stateKey } from '@/admin/lib/galleryPublish'
import { xsrfHeader } from '@/admin/lib/multipartUpload'
import Images from '@/admin/pages/Galleries/panes/Images.vue'
import Settings from '@/admin/pages/Galleries/panes/Settings.vue'
import { useLiveProps } from '@/admin/lib/liveReload'

const { t } = useI18n()

// Was nicht zum offenen Bereich gehört, reist nicht mit — Inertia holt es
// beim Wechsel als Teillieferung nach. Deshalb sind diese Blätter optional
// und nicht etwa manchmal leer.
const props = withDefaults(defineProps<{
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
    design: Record<string, string> | null
    offers: { downloads: boolean; lists: boolean; comments: boolean }
    deviates: boolean
    withdrawn: boolean
    prerelease: boolean
    isExternal: boolean
    externalUrl: string | null
    externalPassword: string | null
    info: string | null
    canPublish: boolean
    canMail: boolean
  }
  siblings?: { id: string; title: string | null; assetCount: number; open: boolean }[]
  presets: App.Gallery.Data.GalleryPresetData[]
  figures: {
    published: number | null
    assets: number
    expiresAt: string | null
    marked: number
    downloaded: number
    viewed: number
  }
  sets: { id: string; name: string; count: number; sortKey: string; sortDir: string; density: string }[]
  assets?: { id: string; key: string; setId: string; src: string; filename: string; width: number; height: number; isCover: boolean }[]
  versions?: { version: number; publishedAt: string | null; assetCount: number }[]
  builds?: { id: string; size: string; status: string; parts: number; bytes: number }[]
  lists?: { id: string; name: string | null; owner: string | null; count: number }[]
  activity?: {
    downloads: { who: string; what: string | null; size: string | null; on: string | null }[]
    marks: { who: string | null; what: string; on: string }[]
    comments: { who: string | null; what: string; on: string }[]
    views: { who: string | null; on: string }[]
  }
  failedAssets?: { publicId: string; filename: string; error: string }[]
  pendingUploads?: { id: string; setId: string; filename: string; stage: 'transferring' | 'processing' }[]
  // Bereich, Set und Abschnitt kommen aus dem Pfad. Als ref() warf jedes
  // Neuladen den Menschen zurück auf Bilder/Alle.
  nav: App.Gallery.Data.GalleryNavData
  area: 'bilder' | 'gestaltung' | 'einstellungen' | 'aktivitaet'
  activeSetId: string | null
  settingsSection: 'allgemein' | 'zugang' | 'download' | 'listen' | 'kommentare'
}>(), {
  siblings: () => [],
  assets: () => [],
  versions: () => [],
  builds: () => [],
  lists: () => [],
  activity: () => ({ downloads: [], marks: [], comments: [], views: [] }),
  failedAssets: () => [],
  pendingUploads: () => [],
})

useLiveProps(['gallery', 'siblings', 'figures', 'sets', 'assets', 'versions', 'builds', 'lists', 'activity', 'failedAssets', 'pendingUploads'])

const tab = computed(() => props.area)

// Was der Gast oben sieht, ist das Titelbild — nicht das erste Bild der
// Liste. Solange keins gesetzt ist, greift dieselbe Regel wie im Betrachter:
// das erste. Der Rest der Vorschau ist die Reihe darunter, also alles ohne
// das Titelbild.
const cover = computed(() => props.assets.find((asset) => asset.isCover) ?? props.assets[0])
const beneath = computed(() => props.assets.filter((asset) => asset.id !== cover.value?.id).slice(0, 6))

// C-20 (#257): three sheets, because the questions differ — who took
// something, who marked something, who was here at all.
const SHEETS = ['downloads', 'marks', 'views'] as const
const sheet = ref<string>('downloads')


const publishState = computed(() => ({
  deviates: props.gallery.deviates,
  withdrawn: props.gallery.withdrawn,
  published: props.figures.published,
  assets: props.figures.assets,
}))
const stateWord = computed(() => {
  const key = stateKey(publishState.value)
  if (key !== 'online') return t(key === 'draft' ? 'galleryRecord.stateDraft' : 'galleryRecord.stateWithdrawn')
  return props.figures.expiresAt === null
    ? t('galleryRecord.stateOnline')
    : t('galleryRecord.stateOnlineUntil', { date: new Date(props.figures.expiresAt).toLocaleDateString('de-DE') })
})
const stateTone = computed(() => ({ online: 'text-ok', withdrawn: 'text-ink-2', draft: 'text-ink-2' })[stateKey(publishState.value)])
const publishLabel = computed(() => publishLabelKey(publishState.value))
const showPublish = computed(() => props.gallery.canPublish && showPublishRule(publishState.value))
const showWithdraw = computed(() => props.gallery.canPublish && showWithdrawRule(publishState.value))

function publish() {
  if (props.gallery.canMail) {
    router.visit(`/galerien/${props.gallery.id}/veroeffentlichen`)
    return
  }

  publishSilently()
}

const sharing = ref(false)
const previewForm = ref<HTMLFormElement | null>(null)

function publishSilently() {
  router.post(`/galerien/${props.gallery.id}/veroeffentlichen`, {}, { preserveScroll: true, onSuccess: () => (sharing.value = true) })
}

function withdraw() {
  router.delete(`/galerien/${props.gallery.id}/veroeffentlichung`, { preserveScroll: true })
}

// R39: only offerable before the first publish — the same state the
// GalleryPrereleaseController guard checks server-side.
const showStartPrerelease = computed(() => props.gallery.canPublish && !props.gallery.isExternal && !props.gallery.prerelease && props.figures.published === null)
const showStopPrerelease = computed(() => props.gallery.canPublish && props.gallery.prerelease)

function startPrerelease() {
  router.post(`/galerien/${props.gallery.id}/pre-release`, {}, { preserveScroll: true })
}

function stopPrerelease() {
  router.delete(`/galerien/${props.gallery.id}/pre-release`, { preserveScroll: true })
}

// R31: JSON, not Inertia — each mints a pass and hands back the guest url,
// nothing here reloads the page.
async function mintPreviewUrl(path: string): Promise<string | null> {
  const response = await fetch(`/galerien/${props.gallery.id}/${path}`, {
    method: 'POST',
    headers: { ...xsrfHeader(), Accept: 'application/json' },
  })

  return response.ok ? ((await response.json()) as { url: string }).url : null
}

// Minor (fix round 1): a failed mint (403, stale CSRF, network) used to be a
// silent no-op — no tab, nothing copied, nothing said — and a successful
// copy gave no confirmation either, so the studio could not tell one from
// the other. actionStatus is the one place both outcomes surface, next to
// the buttons that triggered them. `url` is set only when a link exists but
// could not be copied — shown selectable rather than lost, and left on
// screen (no auto-clear) until the next action replaces it.
const csrf = typeof document !== 'undefined' ? (document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '') : ''

const actionStatus = ref<{ ok: boolean; message: string; url?: string } | null>(null)
let actionStatusTimer: ReturnType<typeof setTimeout> | undefined

function flashStatus(ok: boolean, message: string, url?: string) {
  clearTimeout(actionStatusTimer)
  actionStatus.value = { ok, message, url }
  if (!url) {
    actionStatusTimer = setTimeout(() => (actionStatus.value = null), 3000)
  }
}

// Fix round 3: `await navigator.clipboard?.writeText(url)` used to await
// undefined outright when the browser offered no Clipboard API (any
// non-secure context) — the optional chain short-circuits the call, but
// `await` sits outside it, so execution reached flashStatus(true, …)
// regardless and reported a copy that never happened, onto a link that
// then existed nowhere at all. Checked explicitly instead, and the link
// itself is shown when it cannot be copied — a link the studio can select
// beats a message about one it cannot.
async function createPreviewLink() {
  const url = await mintPreviewUrl('vorschau-link')
  if (!url) {
    flashStatus(false, t('galleryRecord.actionFailed'))
    return
  }

  if (!navigator.clipboard) {
    flashStatus(false, t('galleryRecord.actionFailed'), url)
    return
  }

  try {
    await navigator.clipboard.writeText(url)
    flashStatus(true, t('gallery.copied'))
  } catch {
    // Permission denied, same as no API at all: the link still exists,
    // still shown, still nothing lost.
    flashStatus(false, t('galleryRecord.actionFailed'), url)
  }
}

function copyLink() {
  navigator.clipboard?.writeText(props.gallery.url)
    .then(() => flashStatus(true, t('gallery.copied')))
    .catch(() => flashStatus(false, t('galleryRecord.actionFailed')))
}

const savingPreset = ref(false)
const presetForm = useForm({ name: props.gallery.title ?? '' })

function saveAsPreset() {
  presetForm.post(`/galerien/${props.gallery.id}/vorlage`, {
    preserveScroll: true,
    onSuccess: () => (savingPreset.value = false),
  })
}

const applyingPreset = ref(false)
const applyForm = useForm({ gallery_preset_id: '' })

// Important (fix round 1): downloads/lists/comments go live the instant
// this posts on an already-published gallery — unlike design, which only
// creates a draft deviation until the next Veröffentlichen. The dialog
// names exactly what the chosen preset will overwrite, the same way
// Create.vue's own fields already do by reflecting the preset's values.
const selectedPreset = computed(() => props.presets.find((row) => row.id === applyForm.gallery_preset_id) ?? null)
const presetChanges = computed(() => {
  const preset = selectedPreset.value
  if (!preset) return []

  const onOff = (value: boolean) => t(value ? 'galleryPresets.on' : 'galleryPresets.off')
  const changes: string[] = []
  if (preset.design !== null) changes.push(t('galleryRecord.tab.gestaltung'))
  if (preset.downloadsEnabled !== null) changes.push(`${t('galleryRecord.settingsNav.download')}: ${onOff(preset.downloadsEnabled)}`)
  if (preset.listsEnabled !== null) changes.push(`${t('galleryRecord.settingsNav.listen')}: ${onOff(preset.listsEnabled)}`)
  if (preset.commentsEnabled !== null) changes.push(`${t('galleryRecord.settingsNav.kommentare')}: ${onOff(preset.commentsEnabled)}`)

  return changes
})

function applyPreset() {
  applyForm.post(`/galerien/${props.gallery.id}/vorlage-anwenden`, {
    preserveScroll: true,
    onSuccess: () => (applyingPreset.value = false),
  })
}

const setError = ref<{ id: string; message: string } | null>(null)

function createSet(name: string) {
  setError.value = null
  router.post(`/galerien/${props.gallery.id}/sets`, { name }, {
    preserveScroll: true,
    onError: (errors) => { if (errors.name) setError.value = { id: 'new', message: errors.name } },
  })
}

function renameSet({ id, name }: { id: string; name: string }) {
  setError.value = null
  router.patch(`/galerien/${props.gallery.id}/sets/${id}`, { name }, {
    preserveScroll: true,
    onError: (errors) => { if (errors.name) setError.value = { id, message: errors.name } },
  })
}

function removeSet({ id, targetSetId }: { id: string; targetSetId: string | null }) {
  router.delete(`/galerien/${props.gallery.id}/sets/${id}`, {
    data: { target_set_id: targetSetId },
    preserveScroll: true,
  })
}

function reorderSets(order: string[]) {
  router.patch(`/galerien/${props.gallery.id}/sets/reihenfolge`, { order }, { preserveScroll: true })
}

const design = useForm({ design: props.gallery.design ?? { cover: 'full', type: 'serif', colour: 'light', grid: 'wide' } })

function saveDesign() {
  design.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const COVERS = ['full', 'split', 'plain']
const COLOURS = ['light', 'dark', 'warm']
const GRIDS = ['wide', 'dense', 'single']

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'
}

function megabytes(bytes: number) {
  return `${Math.round(bytes / 1_000_000)} MB`
}
</script>

<template>
  <AdminLayout active="galerien">
    <Page wide>
    <div data-tokens class="min-w-0 flex-1">
      <PageHead :title="gallery.title ?? '—'">
        <span class="flex flex-none items-center gap-1.5 text-small font-semibold" :class="stateTone">
          <span class="size-1.5 rounded-full bg-current" />{{ stateWord }}
        </span>
        <span
          v-if="gallery.prerelease"
          class="flex-none rounded-[var(--r-sm)] bg-due-bg px-2 py-[3px] text-small leading-[1.5] font-semibold text-due"
        >{{ $t('galleryRecord.prerelease') }}</span>
        <span v-if="actionStatus" class="ml-auto flex items-center gap-1.5">
          <span class="text-small" :class="actionStatus.ok ? 'text-ink-2' : 'text-destructive'">{{ actionStatus.message }}</span>
          <Input
            v-if="actionStatus.url"
            :model-value="actionStatus.url"
            readonly
            type="text"
            class="w-[220px] text-small"
            @focus="($event.target as HTMLInputElement).select()"
          />
        </span>

        <span class="flex flex-wrap gap-2" :class="!actionStatus && 'ml-auto'">
          <!-- R36: modal (reka-ui's default) runs useHideOthers() the
               moment force-mount mounts the content, aria-hiding #app from
               page load regardless of open state — a Critical fix round
               caught this hiding the whole record screen from assistive
               tech, permanently. A small action menu has no business
               trapping focus or hiding the page behind it either way. -->
          <DropdownMenu :modal="false">
            <DropdownMenuTrigger as-child>
              <Button type="button" variant="pill-muted">{{ $t('galleryRecord.more') }}</Button>
            </DropdownMenuTrigger>
            <!-- force-mount + data-[state=closed]:hidden rather than the
                 default open-only mount: Record.test.ts SSR-renders this
                 page as a string, and Presence unmounts closed content
                 entirely — the only way the menu's own rules (canPublish
                 gating the preview link, presets.length gating "Vorlage
                 anwenden") stay pinned by a test that can fail. -->
            <DropdownMenuContent force-mount class="data-[state=closed]:hidden">
              <DropdownMenuItem v-if="showPublish || !showWithdraw" @select="sharing = true">{{ $t('galleryRecord.share') }}</DropdownMenuItem>
              <DropdownMenuItem @select="copyLink">{{ $t('galleryRecord.copyLink') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="gallery.canPublish" @select="createPreviewLink">{{ $t('galleryRecord.createPreviewLink') }}</DropdownMenuItem>
              <DropdownMenuItem @select="savingPreset = true">{{ $t('galleryRecord.saveAsPreset') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="presets.length" @select="applyingPreset = true">{{ $t('galleryRecord.applyPreset') }}</DropdownMenuItem>
              <DropdownMenuItem class="min-[641px]:hidden" @select="previewForm?.submit()">{{ $t('galleryRecord.preview') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="showPublish && gallery.canMail" @select="publishSilently">{{ $t('galleryRecord.publishSilently') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="showWithdraw" @select="withdraw">{{ $t('galleryRecord.withdraw') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="showStartPrerelease" @select="startPrerelease">{{ $t('galleryRecord.prerelease') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="showStopPrerelease" @select="stopPrerelease">{{ $t('galleryRecord.prereleaseStop') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <form
            ref="previewForm"
            :action="`/galerien/${gallery.id}/vorschau`"
            method="post"
            target="_blank"
            class="inline-flex max-[640px]:hidden"
          >
            <input type="hidden" name="_token" :value="csrf">
            <Button type="submit" variant="pill-muted">{{ $t('galleryRecord.preview') }}</Button>
          </form>
          <Button v-if="showPublish" type="button" @click="publish">{{ $t(publishLabel) }}</Button>
          <Button v-else-if="showWithdraw" type="button" @click="sharing = true">{{ $t('galleryRecord.share') }}</Button>
        </span>
      </PageHead>

      <GalleryShareDialog v-model:open="sharing" :gallery="gallery" />

      <Dialog v-model:open="savingPreset">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{ $t('galleryRecord.saveAsPreset') }}</DialogTitle>
          </DialogHeader>

          <DialogBody>
            <form id="gallery-preset-form" class="flex flex-col gap-1.5" @submit.prevent="saveAsPreset">
              <Label for="preset-name">{{ $t('galleryPresets.nameField') }}</Label>
              <Input id="preset-name" v-model="presetForm.name" type="text" maxlength="120" />
              <p v-if="presetForm.errors.name" class="text-small leading-[1.45] text-destructive">{{ presetForm.errors.name }}</p>
            </form>
          </DialogBody>

          <DialogFooter>
            <Button type="submit" form="gallery-preset-form" :disabled="presetForm.processing || presetForm.name === ''">
              {{ $t('common.save') }}
            </Button>
            <DialogClose as-child>
              <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog v-model:open="applyingPreset">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{ $t('galleryRecord.applyPreset') }}</DialogTitle>
          </DialogHeader>

          <DialogBody>
            <form id="gallery-preset-apply-form" class="flex flex-col gap-1.5" @submit.prevent="applyPreset">
              <Label for="preset-apply">{{ $t('galleryRecord.applyPresetField') }}</Label>
              <Select v-model="applyForm.gallery_preset_id">
                <SelectTrigger id="preset-apply"><SelectValue :placeholder="$t('galleryRecord.applyPresetPlaceholder')" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="preset in presets" :key="preset.id" :value="preset.id">{{ preset.name }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="presetChanges.length" class="text-small leading-[1.45] text-faint">
                {{ $t('galleryRecord.applyPresetChanges') }} {{ presetChanges.join(' · ') }}
              </p>
            </form>
          </DialogBody>

          <DialogFooter>
            <Button type="submit" form="gallery-preset-apply-form" :disabled="applyForm.processing || applyForm.gallery_preset_id === ''">
              {{ $t('galleryRecord.applyPreset') }}
            </Button>
            <DialogClose as-child>
              <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <div class="flex max-[640px]:flex-col">
        <GalleryNav
          :nav="nav"
          :area="area"
          :active-set-id="activeSetId"
          :error="setError"
          :gallery-id="gallery.id"
          @create="createSet"
          @rename="renameSet"
          @remove="removeSet"
          @reorder="reorderSets"
        />

        <div v-if="tab === 'bilder'" class="flex min-w-0 flex-1 flex-col min-[1280px]:flex-row">
          <div class="min-w-0 flex-1">
            <Images
              :gallery="gallery"
              :sets="sets"
              :assets="assets"
              :active-set-id="activeSetId"
              :failed-assets="failedAssets"
              :pending-uploads="pendingUploads"
            />
          </div>
          <aside class="flex-none px-6 pt-5 pb-7 min-[1280px]:sticky min-[1280px]:top-0 min-[1280px]:w-[280px] min-[1280px]:self-start min-[1280px]:pl-0">
            <dl class="m-0 flex flex-col gap-2.5 text-small">
              <div>
                <dt class="text-ink-2">{{ $t('galleryRecord.published') }}</dt>
                <dd class="m-0 mt-1 font-semibold" :class="stateTone">{{ stateWord }}</dd>
                <dd v-if="figures.published !== null" class="m-0 mt-0.5 text-ink-2">{{ $t('galleryRecord.version', { version: figures.published }) }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-ink-2">{{ $t('galleryRecord.viewed') }}</dt>
                <dd class="m-0 tabular-nums">{{ figures.viewed }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-ink-2">{{ $t('galleryRecord.marked') }}</dt>
                <dd class="m-0 tabular-nums">{{ figures.marked }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-ink-2">{{ $t('galleryRecord.downloaded') }}</dt>
                <dd class="m-0 tabular-nums">{{ figures.downloaded }}</dd>
              </div>
            </dl>
            <button type="button" class="mt-4 cursor-pointer border-0 bg-transparent p-0 text-small text-ring hover:underline" @click="sharing = true">
              {{ $t('galleryRecord.share') }}
            </button>
          </aside>
        </div>
        <!-- Gestaltung: choosing without seeing is guessing, so the choice is
             on the left and the result on the right. -->
        <div v-else-if="tab === 'gestaltung'" class="flex min-w-0 flex-1 max-[640px]:flex-col">
          <div class="w-80 flex-none pt-5 pr-4 pb-7 pl-8 max-[640px]:w-auto max-[640px]:pl-4">
            <div class="mb-4 flex flex-col gap-1">
              <span class="pl-0.5 text-small leading-none font-normal text-ink-2">{{ $t('galleryRecord.cover') }}</span>
              <ChoiceGroup
                v-model="design.design.cover"
                :options="COVERS.map((option) => ({ value: option, label: $t(`galleryRecord.cover_${option}`) }))"
                @update:model-value="saveDesign"
              />
            </div>

            <div class="mb-4 flex flex-col gap-1">
              <span class="pl-0.5 text-small leading-none font-normal text-ink-2">{{ $t('galleryRecord.colour') }}</span>
              <ChoiceGroup
                v-model="design.design.colour"
                :options="COLOURS.map((option) => ({ value: option, label: $t(`galleryRecord.colour_${option}`) }))"
                @update:model-value="saveDesign"
              />
            </div>

            <div class="flex flex-col gap-1">
              <span class="pl-0.5 text-small leading-none font-normal text-ink-2">{{ $t('galleryRecord.grid') }}</span>
              <ChoiceGroup
                v-model="design.design.grid"
                :options="GRIDS.map((option) => ({ value: option, label: $t(`galleryRecord.grid_${option}`) }))"
                @update:model-value="saveDesign"
              />
            </div>
          </div>

          <div class="min-w-0 flex-1 bg-sunk px-6 pt-5 pb-7">
            <div class="overflow-hidden rounded-[12px] bg-paper">
              <div class="relative">
                <img v-if="cover?.src" class="block aspect-video w-full object-cover" :src="`${cover.src}?w=1200&fmt=jpeg`" alt="" />
                <div class="absolute inset-x-0 bottom-0 bg-[linear-gradient(transparent,rgba(20,16,14,0.55))] px-5 py-[22px] text-center text-paper">
                  <b class="block text-title leading-[1.2] font-semibold">{{ gallery.title }}</b>
                  <span class="text-small opacity-85">{{ $t('contacts.assetCount', figures.assets) }}</span>
                </div>
              </div>
              <div class="grid grid-cols-3 gap-1.5 p-2.5">
                <img
                  v-for="asset in beneath"
                  :key="asset.id"
                  class="block aspect-3/2 w-full rounded-[3px] object-cover"
                  :src="`${asset.src}?w=400&fmt=jpeg`"
                  alt=""
                  loading="lazy"
                />
              </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
              <a class="ml-auto text-small tracking-[-0.01em] whitespace-nowrap text-ring no-underline hover:underline" :href="gallery.url">{{ $t('galleryRecord.openLive') }}</a>
            </div>
          </div>
        </div>

        <!-- Einstellungen: Pixieset's General Settings shape — a
             sub-navigation in the rail, one labelled section at a time here. -->
        <div v-else-if="tab === 'einstellungen'" class="flex min-w-0 flex-1 px-6 pt-7 pb-8">
          <Settings :gallery="gallery" :siblings="siblings" :versions="versions" :lists="lists" :settings-section="settingsSection" />
        </div>

        <div v-else class="min-w-0 flex-1 px-6 pt-7 pb-8">
          <!-- Marked/downloaded/viewed: a look backwards, so they sit above
               the sheets that spell the same facts out row by row. -->
          <div class="mb-5 flex">
            <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
              <div class="text-small text-ink-2">{{ $t('galleryRecord.marked') }}</div>
              <div class="mt-[5px] text-title leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums">{{ figures.marked }}</div>
            </div>
            <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
              <div class="text-small text-ink-2">{{ $t('galleryRecord.downloaded') }}</div>
              <div class="mt-[5px] text-title leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums">{{ figures.downloaded }}</div>
            </div>
            <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
              <div class="text-small text-ink-2">{{ $t('galleryRecord.viewed') }}</div>
              <div class="mt-[5px] text-title leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums">{{ figures.viewed }}</div>
            </div>
          </div>

          <ChoiceGroup
            v-model="sheet"
            :options="SHEETS.map((name) => ({ value: name, label: $t(`galleryRecord.sheet.${name}`) }))"
            variant="segmented"
          />

          <table class="mt-4 w-full border-collapse max-[640px]:block max-[640px]:overflow-x-auto">
            <thead>
              <tr>
                <th class="px-6 pb-2 text-left text-small leading-none font-semibold text-ink-2">{{ $t('galleryRecord.who') }}</th>
                <th class="px-6 pb-2 text-left text-small leading-none font-semibold text-ink-2">{{ $t('galleryRecord.what') }}</th>
                <th class="px-6 pb-2 text-left text-small leading-none font-semibold text-ink-2 text-right">{{ $t('galleryRecord.when') }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="sheet === 'downloads'">
                <tr v-for="(row, index) in activity.downloads" :key="index">
                  <td class="px-6 py-2.5 align-baseline text-ui font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_var(--rule)]">{{ row.who }}</td>
                  <td class="px-6 py-2.5 align-baseline text-small shadow-[inset_0_1px_0_var(--rule)] text-ink-2">{{ row.what ?? $t('gallery.everything') }} · {{ $t(`gallery.size.${row.size}`) }}</td>
                  <td class="px-6 py-2.5 align-baseline text-small shadow-[inset_0_1px_0_var(--rule)] text-right tabular-nums">{{ day(row.on) }}</td>
                </tr>
              </template>

              <template v-else-if="sheet === 'marks'">
                <tr v-for="(row, index) in activity.marks" :key="index">
                  <td class="px-6 py-2.5 align-baseline text-ui font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_var(--rule)]">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                  <td class="px-6 py-2.5 align-baseline text-small shadow-[inset_0_1px_0_var(--rule)] text-ink-2">{{ row.what }}</td>
                  <td class="px-6 py-2.5 align-baseline text-small shadow-[inset_0_1px_0_var(--rule)] text-right tabular-nums">{{ day(row.on) }}</td>
                </tr>
                <tr v-for="(row, index) in activity.comments" :key="`c${index}`">
                  <td class="px-6 py-2.5 align-baseline text-ui font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_var(--rule)]">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                  <td class="px-6 py-2.5 align-baseline text-small shadow-[inset_0_1px_0_var(--rule)] text-ink-2">{{ row.what }}</td>
                  <td class="px-6 py-2.5 align-baseline text-small shadow-[inset_0_1px_0_var(--rule)] text-right tabular-nums">{{ day(row.on) }}</td>
                </tr>
              </template>

              <template v-else>
                <tr v-for="(row, index) in activity.views" :key="index">
                  <td class="px-6 py-2.5 align-baseline text-ui font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_var(--rule)]">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                  <td class="px-6 py-2.5 align-baseline text-small shadow-[inset_0_1px_0_var(--rule)] text-ink-2">—</td>
                  <td class="px-6 py-2.5 align-baseline text-small shadow-[inset_0_1px_0_var(--rule)] text-right tabular-nums">{{ day(row.on) }}</td>
                </tr>
              </template>
            </tbody>
          </table>

          <!-- Fertige ZIP-Archive: Tatsachen über Vergangenes, keine
               Einstellung — verlässt deshalb den ⚙-Reiter. -->
          <h2 class="mt-9">{{ $t('galleryRecord.downloadsHeading') }}</h2>
          <table v-if="builds.length" class="w-full border-collapse max-[640px]:block max-[640px]:overflow-x-auto">
            <thead>
              <tr>
                <th class="px-6 pb-2 text-left text-small leading-none font-semibold text-ink-2">{{ $t('gallery.sizeLabel') }}</th>
                <th class="px-6 pb-2 text-left text-small leading-none font-semibold text-ink-2">{{ $t('galleryRecord.parts') }}</th>
                <th class="px-6 pb-2 text-left text-small leading-none font-semibold text-ink-2 text-right">{{ $t('galleryRecord.size') }}</th>
                <th class="px-6 pb-2 text-left text-small leading-none font-semibold text-ink-2">{{ $t('galleryRecord.state') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="build in builds" :key="build.id">
                <td class="px-6 py-2.5 align-baseline text-ui font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_var(--rule)]">{{ $t(`gallery.size.${build.size}`) }}</td>
                <td class="px-6 py-2.5 align-baseline text-small shadow-[inset_0_1px_0_var(--rule)] text-ink-2">{{ build.parts }}</td>
                <td class="px-6 py-2.5 align-baseline text-small shadow-[inset_0_1px_0_var(--rule)] text-right tabular-nums">{{ megabytes(build.bytes) }}</td>
                <td class="px-6 py-2.5 align-baseline text-small shadow-[inset_0_1px_0_var(--rule)] text-ink-2">{{ build.status }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-small leading-[1.45] text-faint">{{ $t('galleryRecord.noBuilds') }}</p>
        </div>
      </div>
    </div>
    </Page>
  </AdminLayout>
</template>
