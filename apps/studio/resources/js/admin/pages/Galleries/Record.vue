<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { ChoiceGroup } from '@/ui/choice-group'
import { Button } from '@/ui/button'
import { copyText } from '@/ui/copy-button'
import { HoldToConfirm } from '@/ui/hold-to-confirm'
import { AlertDialog, AlertDialogBody, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/ui/alert-dialog'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import GalleryDesignFields from '@/admin/components/gallery/GalleryDesignFields.vue'
import GalleryPreview from '@/admin/components/gallery/GalleryPreview.vue'
import { complete } from '@/admin/lib/galleryDesign'
import GalleryAssetPicker from '@/admin/components/gallery/GalleryAssetPicker.vue'
import { copyCount, copyIds } from '@/admin/lib/galleryCopySelection'
import { titleMatches } from '@/admin/lib/galleryDelete'
import type { SelectionState } from '@/admin/lib/gallerySelection'
import GalleryQrDialog from '@/admin/components/gallery/GalleryQrDialog.vue'
import type { Permissions } from '@/admin/lib/galleryPermissions'
import GallerySendDialog from '@/admin/components/gallery/GallerySendDialog.vue'
import GalleryNav from '@/admin/components/gallery/GalleryNav.vue'
import { publishLabelKey, showPublish as showPublishRule, showWithdraw as showWithdrawRule, stateKey } from '@/admin/lib/galleryPublish'
import { xsrfHeader } from '@/admin/lib/multipartUpload'
import Images from '@/admin/pages/Galleries/panes/Images.vue'
import Activity, { type ActivityData } from '@/admin/pages/Galleries/panes/Activity.vue'
import Settings from '@/admin/pages/Galleries/panes/Settings.vue'
import Chip from '@/ui/page/Chip.vue'
import { shortDate } from '@/admin/lib/dates'

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
    coverFocal: { x: number; y: number } | null
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
    deviates: boolean
    withdrawn: boolean
    prerelease: boolean
    isExternal: boolean
    externalUrl: string | null
    externalPassword: string | null
    info: string | null
    canPublish: boolean
    canDelete: boolean
    copy: { done: number; total: number } | null
    canMail: boolean
    recipient: { name: string; email: string } | null
    mailMessage?: string | null
    listedOnHome: boolean
    homePinned: boolean
    homeBlocker: 'locked' | 'draft' | 'withdrawn' | 'expired' | 'external' | null
    homeMode: 'none' | 'redirect' | 'overview'
    categoryId: string | null
    categories: { id: string; name: string }[]
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
  sets: { id: string; name: string; count: number; sortKey: string; sortDir: string; density: string; access: 'internal' | 'guests'; url: string }[]
  assets?: { id: string; key: string; setId: string; src: string; filename: string; width: number; height: number; isCover: boolean }[]
  versions?: { version: number; publishedAt: string | null; assetCount: number }[]
  builds?: { id: string; size: string; status: string; parts: number; bytes: number }[]
  lists?: { id: string; name: string | null; owner: string | null; count: number }[]
  activity?: ActivityData
  favoriteKeys?: string[] | null
  failedAssets?: { publicId: string; filename: string; error: string }[]
  pendingUploads?: { id: string; setId: string; filename: string; stage: 'transferring' | 'processing' }[]
  // Bereich, Set und Abschnitt kommen aus dem Pfad. Als ref() warf jedes
  // Neuladen den Menschen zurück auf Bilder/Alle.
  nav: App.Gallery.Data.GalleryNavData
  area: 'bilder' | 'gestaltung' | 'einstellungen' | 'aktivitaet'
  activeSetId: string | null
  settingsSection: 'zugang' | 'download' | 'darstellung'
}>(), {
  siblings: () => [],
  assets: () => [],
  versions: () => [],
  builds: () => [],
  lists: () => [],
  activity: () => ({ counts: { favorites: 0, comments: 0, hidden: 0, downloads: 0 }, events: [], favorites: [], hidden: [], task: { available: false, url: null } }),
  favoriteKeys: null,
  failedAssets: () => [],
  pendingUploads: () => [],
})

const tab = computed(() => props.area)

// Was der Gast oben sieht, ist das Titelbild — nicht das erste Bild der
// Liste. Solange keins gesetzt ist, greift dieselbe Regel wie im Betrachter:
// das erste. Der Rest der Vorschau ist die Reihe darunter, also alles ohne
// das Titelbild.
const cover = computed(() => props.assets.find((asset) => asset.isCover) ?? props.assets[0])
const beneath = computed(() => props.assets.filter((asset) => asset.id !== cover.value?.id).slice(0, 6))

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
    : t('galleryRecord.stateOnlineUntil', { date: shortDate(props.figures.expiresAt) })
})
const stateTone = computed(() => ({ online: 'text-ok', withdrawn: 'text-ink-2', draft: 'text-ink-2' })[stateKey(publishState.value)])
const publishLabel = computed(() => publishLabelKey(publishState.value))
const showPublish = computed(() => props.gallery.canPublish && showPublishRule(publishState.value))
const showWithdraw = computed(() => props.gallery.canPublish && showWithdrawRule(publishState.value))

function publish() {
  openSend(props.gallery.canMail ? 'mail' : 'link')
}

const sharing = ref(false)
const sendTab = ref<'mail' | 'link'>('link')
const showingQr = ref(false)

function openSend(target: 'mail' | 'link') {
  sendTab.value = target
  sharing.value = true
}

const showResend = computed(() => props.gallery.canPublish && props.gallery.canMail && showWithdraw.value && !props.gallery.deviates)
const previewForm = ref<HTMLFormElement | null>(null)

function duplicate() {
  router.post(`/galerien/${props.gallery.id}/duplizieren`)
}

const withdrawing = ref(false)

function withdraw() {
  router.delete(`/galerien/${props.gallery.id}/veroeffentlichung`, { preserveScroll: true })
}

// R39: only offerable before the first publish — the same state the
// GalleryPrereleaseController guard checks server-side.
const showStartPrerelease = computed(() => props.gallery.canPublish && !props.gallery.isExternal && !props.gallery.prerelease && props.figures.published === null)
const showStopPrerelease = computed(() => props.gallery.canPublish && props.gallery.prerelease)

const choosingPrerelease = ref(false)
const prereleaseMode = ref<'version' | 'own'>('version')

const prereleaseSelection = ref<SelectionState>({ selected: new Set(), anchor: null })
const allAssetIds = computed(() => props.assets.map((asset) => asset.id))

// Die Bilder gehören nur zu zwei Bereichen; hier werden sie erst bei Bedarf nachgeladen.
watch([choosingPrerelease, prereleaseMode], ([open, mode]) => {
  if (open && mode === 'own' && props.assets.length === 0 && props.figures.assets > 0) router.reload({ only: ['assets'] })
})

function startPrerelease() {
  const own = prereleaseMode.value === 'own'

  router.post(`/galerien/${props.gallery.id}/pre-release`, { own, asset_ids: own ? copyIds(prereleaseSelection.value.selected, allAssetIds.value) : null }, {
    preserveScroll: true,
    onSuccess: () => (choosingPrerelease.value = false),
  })
}

const deleting = ref(false)
const typedTitle = ref('')

function destroy() {
  router.delete(`/galerien/${props.gallery.id}`, { data: { title: typedTitle.value } })
}

function resumeCopy() {
  router.post(`/galerien/${props.gallery.id}/kopie-fortsetzen`, {}, { preserveScroll: true })
}

function requestArchive() {
  router.post('/einstellungen/export/archiv', { gallery: props.gallery.id }, { preserveScroll: true })
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

  if (await copyText(url)) flashStatus(true, t('gallery.copied'))
  else flashStatus(false, t('galleryRecord.actionFailed'), url)
}

async function copyLink() {
  if (await copyText(props.gallery.url)) flashStatus(true, t('gallery.copied'))
  else flashStatus(false, t('galleryRecord.actionFailed'))
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

const design = useForm({ design: complete(props.gallery.design) })

function saveDesign() {
  design.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

function saveFocal(focal: { x: number; y: number }) {
  router.patch(`/galerien/${props.gallery.id}/titelbild`, { asset_id: cover.value.id, focal_x: focal.x, focal_y: focal.y }, { preserveScroll: true })
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
        <Chip v-if="gallery.prerelease" tone="warn">{{ $t('galleryRecord.prerelease') }}</Chip>
        <template v-if="gallery.copy">
          <Chip tone="warn">{{ $t('galleryRecord.copying', gallery.copy) }}</Chip>
          <Button type="button" variant="outline" @click="resumeCopy">{{ $t('galleryRecord.copyResume') }}</Button>
        </template>
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
              <Button type="button" variant="secondary">{{ $t('galleryRecord.more') }}</Button>
            </DropdownMenuTrigger>
            <!-- force-mount + data-[state=closed]:hidden rather than the
                 default open-only mount: Record.test.ts SSR-renders this
                 page as a string, and Presence unmounts closed content
                 entirely — the only way the menu's own rules (canPublish
                 gating the preview link, presets.length gating "Vorlage
                 anwenden") stay pinned by a test that can fail. -->
            <DropdownMenuContent force-mount class="data-[state=closed]:hidden">
              <DropdownMenuItem v-if="showPublish || !showWithdraw" @select="openSend('link')">{{ $t('galleryRecord.share') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="showResend" @select="openSend('mail')">{{ $t('galleryRecord.resend') }}</DropdownMenuItem>
              <DropdownMenuItem @select="copyLink">{{ $t('galleryRecord.copyLink') }}</DropdownMenuItem>
              <DropdownMenuItem @select="showingQr = true">{{ $t('galleryRecord.qr') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="!gallery.isExternal" @select="duplicate">{{ $t('galleryRecord.duplicate') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="gallery.canPublish" @select="createPreviewLink">{{ $t('galleryRecord.createPreviewLink') }}</DropdownMenuItem>
              <DropdownMenuItem @select="savingPreset = true">{{ $t('galleryRecord.saveAsPreset') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="presets.length" @select="applyingPreset = true">{{ $t('galleryRecord.applyPreset') }}</DropdownMenuItem>
              <DropdownMenuItem class="min-[641px]:hidden" @select="previewForm?.submit()">{{ $t('galleryRecord.preview') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="showWithdraw" @select="withdrawing = true">{{ $t('galleryRecord.withdraw') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="showStartPrerelease" @select="choosingPrerelease = true">{{ $t('galleryRecord.prerelease') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="showStopPrerelease" @select="stopPrerelease">{{ $t('galleryRecord.prereleaseStop') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="gallery.canDelete" class="text-destructive" @select="typedTitle = ''; deleting = true">{{ $t('common.delete') }}</DropdownMenuItem>
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
            <Button type="submit" variant="secondary">{{ $t('galleryRecord.preview') }}</Button>
          </form>
          <Button class="ml-auto" v-if="showPublish" type="button" @click="publish">{{ $t(publishLabel) }}</Button>
          <Button class="ml-auto" v-else-if="showWithdraw" type="button" @click="openSend('link')">{{ $t('galleryRecord.share') }}</Button>
        </span>
      </PageHead>

      <GallerySendDialog
        v-model:open="sharing"
        v-model:tab="sendTab"
        :gallery="gallery"
        :recipient="gallery.recipient"
        :publishable="showPublish"
      />
      <GalleryQrDialog v-model:open="showingQr" :gallery="gallery" />

      <AlertDialog v-model:open="withdrawing">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{{ $t('galleryRecord.withdrawTitle') }}</AlertDialogTitle>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <HoldToConfirm tone="destructive" @confirm="withdrawing = false; withdraw()">{{ $t('galleryRecord.withdraw') }}</HoldToConfirm>
            <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog v-model:open="deleting">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{{ $t('galleryRecord.deleteTitle') }}</AlertDialogTitle>
          </AlertDialogHeader>
          <AlertDialogBody class="flex flex-col gap-3">
            <Button type="button" variant="outline" class="self-start" @click="requestArchive">{{ $t('galleryRecord.deleteArchive') }}</Button>
            <div v-if="gallery.title" class="flex flex-col gap-1.5">
              <Label for="gallery-delete-title">{{ $t('galleryRecord.deleteConfirm', { title: gallery.title }) }}</Label>
              <Input id="gallery-delete-title" v-model="typedTitle" type="text" autocomplete="off" />
            </div>
          </AlertDialogBody>
          <AlertDialogFooter>
            <HoldToConfirm tone="destructive" :disabled="!titleMatches(typedTitle, gallery.title)" @confirm="deleting = false; destroy()">{{ $t('common.delete') }}</HoldToConfirm>
            <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <Dialog v-model:open="choosingPrerelease">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{ $t('galleryRecord.prerelease') }}</DialogTitle>
          </DialogHeader>

          <DialogBody>
            <ChoiceGroup
              v-model="prereleaseMode"
              :options="(['version', 'own'] as const).map((value) => ({ value, label: $t(`galleryRecord.prereleaseMode.${value}`) }))"
            />
            <template v-if="prereleaseMode === 'own' && assets.length">
              <p class="mt-3 text-small text-ink-2">{{ $t('galleryRecord.prereleasePick', { count: copyCount(prereleaseSelection.selected, allAssetIds) }) }}</p>
              <GalleryAssetPicker v-model="prereleaseSelection" :assets="assets" class="mt-1.5" />
            </template>
          </DialogBody>

          <DialogFooter>
            <Button class="ml-auto" type="button" @click="startPrerelease">{{ $t('galleryRecord.prereleaseStart') }}</Button>
            <DialogClose as-child>
              <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog v-model:open="savingPreset">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{ $t('galleryRecord.saveAsPreset') }}</DialogTitle>
          </DialogHeader>

          <DialogBody>
            <form id="gallery-preset-form" class="flex flex-col gap-1.5" @submit.prevent="saveAsPreset">
              <Label for="preset-name">{{ $t('galleryPresets.nameField') }}</Label>
              <Input id="preset-name" v-model="presetForm.name" type="text" maxlength="120" required />
              <p v-if="presetForm.errors.name" class="text-small leading-[1.45] text-destructive">{{ presetForm.errors.name }}</p>
            </form>
          </DialogBody>

          <DialogFooter>
            <Button class="ml-auto" type="submit" form="gallery-preset-form" :disabled="presetForm.processing">
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
              <Select v-model="applyForm.gallery_preset_id" required>
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
            <Button class="ml-auto" type="submit" form="gallery-preset-apply-form" :disabled="applyForm.processing">
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
              :favorite-keys="favoriteKeys"
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
            <button type="button" class="mt-4 cursor-pointer border-0 bg-transparent p-0 text-small text-ring hover:underline" @click="openSend('link')">
              {{ $t('galleryRecord.share') }}
            </button>
          </aside>
        </div>
        <!-- Gestaltung: choosing without seeing is guessing, so the choice is
             on the left and the result on the right. -->
        <div v-else-if="tab === 'gestaltung'" class="flex min-w-0 flex-1 max-[640px]:flex-col">
          <div class="w-[360px] flex-none pt-5 pr-4 pb-7 pl-8 max-[640px]:w-auto max-[640px]:pl-4">
            <GalleryDesignFields
              v-model="design.design"
              :cover="cover?.src ? { src: cover.src, focal: gallery.coverFocal } : null"
              @focal="saveFocal"
              @update:model-value="saveDesign"
            />
          </div>

          <div class="min-w-0 flex-1 bg-sunk px-6 pt-5 pb-7">
            <GalleryPreview
              :design="design.design"
              :offers="gallery.offers"
              :photos="cover?.src ? { cover: `${cover.src}?w=1200&fmt=jpeg`, focal: gallery.coverFocal, rest: beneath.map((asset) => `${asset.src}?w=400&fmt=jpeg`) } : null"
              :title="gallery.title ?? ''"
              :url="gallery.url"
            />

            <div class="mt-4 flex items-center gap-3">
              <a class="ml-auto text-small tracking-[-0.01em] whitespace-nowrap text-ring no-underline hover:underline" :href="gallery.url">{{ $t('galleryRecord.openLive') }}</a>
            </div>
          </div>
        </div>

        <!-- Einstellungen: Pixieset's General Settings shape — a
             sub-navigation in the rail, one labelled section at a time here. -->
        <div v-else-if="tab === 'einstellungen'" class="flex min-w-0 flex-1 px-6 pt-7 pb-8">
          <Settings :gallery="gallery" :siblings="siblings" :settings-section="settingsSection" />
        </div>

        <div v-else class="flex min-w-0 flex-1 px-6 pt-7 pb-8">
          <Activity :gallery-id="gallery.id" :activity="activity" :builds="builds" :lists="lists" :versions="versions" />
        </div>
      </div>
    </div>
    </Page>
  </AdminLayout>
</template>
