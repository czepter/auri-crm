<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { ChoiceGroup } from '@/ui/choice-group'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { NativeSelect } from '@/ui/native-select'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import GalleryRail from '@/admin/components/gallery/GalleryRail.vue'
import { initialActiveSetId, nextActiveSetId } from '@/admin/lib/gallerySets'
import { publishLabelKey, showPublish as showPublishRule, showWithdraw as showWithdrawRule } from '@/admin/lib/galleryPublish'
import { xsrfHeader } from '@/admin/lib/multipartUpload'
import Images from '@/admin/pages/Galleries/panes/Images.vue'
import Settings from '@/admin/pages/Galleries/panes/Settings.vue'

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
    design: Record<string, string> | null
    offers: { downloads: boolean; lists: boolean; comments: boolean }
    deviates: boolean
    withdrawn: boolean
    canPublish: boolean
  }
  siblings: { id: string; title: string | null; assetCount: number; open: boolean }[]
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
  assets: { id: string; key: string; setId: string; src: string; filename: string; width: number; height: number; isCover: boolean }[]
  versions: { version: number; publishedAt: string | null; assetCount: number }[]
  builds: { id: string; size: string; status: string; parts: number; bytes: number }[]
  lists: { id: string; name: string | null; owner: string | null; count: number }[]
  activity: {
    downloads: { who: string; what: string | null; size: string | null; on: string | null }[]
    marks: { who: string | null; what: string; on: string }[]
    comments: { who: string | null; what: string; on: string }[]
    views: { who: string | null; on: string }[]
  }
  failedAssets: { publicId: string; filename: string; error: string }[]
  pendingUploads: { id: string; setId: string; filename: string; stage: 'transferring' | 'processing' }[]
}>()

const tab = ref<'bilder' | 'gestaltung' | 'einstellungen' | 'aktivitaet'>('bilder')
const settingsSection = ref<'allgemein' | 'zugang' | 'download' | 'listen' | 'kommentare'>('allgemein')

// C-20 (#257): three sheets, because the questions differ — who took
// something, who marked something, who was here at all.
const SHEETS = ['downloads', 'marks', 'views'] as const
const sheet = ref<string>('downloads')

const activeSetId = ref<string | null>(initialActiveSetId(props.sets))
const coverSrc = computed(() => props.assets.find((asset) => asset.isCover)?.src ?? null)

const publishState = computed(() => ({
  deviates: props.gallery.deviates,
  withdrawn: props.gallery.withdrawn,
  published: props.figures.published,
  assets: props.figures.assets,
}))
const publishLabel = computed(() => publishLabelKey(publishState.value))
const showPublish = computed(() => props.gallery.canPublish && showPublishRule(publishState.value))
const showWithdraw = computed(() => props.gallery.canPublish && showWithdrawRule(publishState.value))

function publish() {
  router.post(`/galerien/${props.gallery.id}/veroeffentlichen`, {}, { preserveScroll: true })
}

function withdraw() {
  router.delete(`/galerien/${props.gallery.id}/veroeffentlichung`, { preserveScroll: true })
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

async function openPreview() {
  const url = await mintPreviewUrl('vorschau')
  if (url) window.open(url, '_blank')
}

async function createPreviewLink() {
  const url = await mintPreviewUrl('vorschau-link')
  if (url) await navigator.clipboard?.writeText(url)
}

function copyLink() {
  void navigator.clipboard?.writeText(props.gallery.url)
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

function applyPreset() {
  applyForm.post(`/galerien/${props.gallery.id}/vorlage-anwenden`, {
    preserveScroll: true,
    onSuccess: () => (applyingPreset.value = false),
  })
}

watch(
  () => props.sets,
  (rows) => (activeSetId.value = nextActiveSetId(rows, activeSetId.value)),
)

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
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'
}

function megabytes(bytes: number) {
  return `${Math.round(bytes / 1_000_000)} MB`
}
</script>

<template>
  <AdminLayout active="galerien">
    <div data-tokens>
      <PageHead :title="gallery.title ?? '—'">
        <!-- Stand, chip-styled: the same fact the old counter band spent a
             whole column on, and the only one of the six worth a glance at
             every tab (ProjectShell's isBooked chip is the pattern). -->
        <span
          v-if="figures.published !== null"
          class="flex-none rounded-[var(--r-sm)] bg-ok-bg px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase text-ok"
        >{{ $t('galleryRecord.version', { version: figures.published }) }}</span>

        <span class="ml-auto flex gap-2">
          <DropdownMenu>
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
              <DropdownMenuItem @select="copyLink">{{ $t('galleryRecord.copyLink') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="gallery.canPublish" @select="createPreviewLink">{{ $t('galleryRecord.createPreviewLink') }}</DropdownMenuItem>
              <DropdownMenuItem @select="savingPreset = true">{{ $t('galleryRecord.saveAsPreset') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="presets.length" @select="applyingPreset = true">{{ $t('galleryRecord.applyPreset') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <Button type="button" variant="pill-muted" @click="openPreview">{{ $t('galleryRecord.preview') }}</Button>
          <Button v-if="showWithdraw" type="button" variant="pill-muted" @click="withdraw">{{ $t('galleryRecord.withdraw') }}</Button>
          <Button v-if="showPublish" type="button" @click="publish">{{ $t(publishLabel) }}</Button>
        </span>
      </PageHead>

      <Dialog v-model:open="savingPreset">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{ $t('galleryRecord.saveAsPreset') }}</DialogTitle>
          </DialogHeader>

          <DialogBody>
            <form id="gallery-preset-form" class="flex flex-col gap-1.5" @submit.prevent="saveAsPreset">
              <Label for="preset-name">{{ $t('galleryPresets.nameField') }}</Label>
              <Input id="preset-name" v-model="presetForm.name" type="text" maxlength="120" />
              <p v-if="presetForm.errors.name" class="text-[11.5px] leading-[1.45] text-destructive">{{ presetForm.errors.name }}</p>
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
              <NativeSelect id="preset-apply" v-model="applyForm.gallery_preset_id">
                <option value="" disabled>{{ $t('galleryPresets.none') }}</option>
                <option v-for="preset in presets" :key="preset.id" :value="preset.id">{{ preset.name }}</option>
              </NativeSelect>
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

      <div class="flex">
        <GalleryRail
          v-model:tab="tab"
          v-model:active-set-id="activeSetId"
          v-model:settings-section="settingsSection"
          :cover-src="coverSrc"
          :sets="sets"
          :error="setError"
          :offers="gallery.offers"
          @create="createSet"
          @rename="renameSet"
          @remove="removeSet"
          @reorder="reorderSets"
        />

        <!-- Bilder: the pane owns the grid and selection. Fassungen moved to
             Einstellungen — the count sits beside the set name and Stand is
             the header's own chip, so nothing here repeats them. -->
        <div v-if="tab === 'bilder'" class="min-w-0 flex-1">
          <Images
            :gallery="gallery"
            :sets="sets"
            :assets="assets"
            :active-set-id="activeSetId"
            :failed-assets="failedAssets"
            :pending-uploads="pendingUploads"
          />
        </div>

        <!-- Gestaltung: choosing without seeing is guessing, so the choice is
             on the left and the result on the right. -->
        <div v-else-if="tab === 'gestaltung'" class="flex min-w-0 flex-1">
          <div class="w-80 flex-none pt-5 pr-4 pb-7 pl-8">
            <div class="mb-4 flex flex-col gap-1">
              <span class="pl-0.5 text-[10.5px] leading-none font-normal tracking-[0.05em] uppercase text-ink-2">{{ $t('galleryRecord.cover') }}</span>
              <ChoiceGroup
                v-model="design.design.cover"
                :options="COVERS.map((option) => ({ value: option, label: $t(`galleryRecord.cover_${option}`) }))"
                @update:model-value="saveDesign"
              />
            </div>

            <div class="mb-4 flex flex-col gap-1">
              <span class="pl-0.5 text-[10.5px] leading-none font-normal tracking-[0.05em] uppercase text-ink-2">{{ $t('galleryRecord.colour') }}</span>
              <ChoiceGroup
                v-model="design.design.colour"
                :options="COLOURS.map((option) => ({ value: option, label: $t(`galleryRecord.colour_${option}`) }))"
                @update:model-value="saveDesign"
              />
            </div>

            <div class="flex flex-col gap-1">
              <span class="pl-0.5 text-[10.5px] leading-none font-normal tracking-[0.05em] uppercase text-ink-2">{{ $t('galleryRecord.grid') }}</span>
              <ChoiceGroup
                v-model="design.design.grid"
                :options="GRIDS.map((option) => ({ value: option, label: $t(`galleryRecord.grid_${option}`) }))"
                @update:model-value="saveDesign"
              />
            </div>
          </div>

          <div class="min-w-0 flex-1 bg-sunk px-8 pt-5 pb-7">
            <div class="overflow-hidden rounded-[12px] bg-paper">
              <div class="relative">
                <img v-if="assets[0]?.src" class="block aspect-video w-full object-cover" :src="`${assets[0].src}?w=1200&fmt=jpeg`" alt="" />
                <div class="absolute inset-x-0 bottom-0 bg-[linear-gradient(transparent,rgba(20,16,14,0.55))] px-5 py-[22px] text-center text-paper">
                  <b class="block text-[19px] leading-[1.2] font-semibold tracking-[0.04em] uppercase">{{ gallery.title }}</b>
                  <span class="text-[11.5px] opacity-85">{{ $t('contacts.assetCount', figures.assets) }}</span>
                </div>
              </div>
              <div class="grid grid-cols-3 gap-1.5 p-2.5">
                <img
                  v-for="asset in assets.slice(1, 7)"
                  :key="asset.id"
                  class="block aspect-3/2 w-full rounded-[3px] object-cover"
                  :src="`${asset.src}?w=400&fmt=jpeg`"
                  alt=""
                  loading="lazy"
                />
              </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
              <a class="ml-auto text-[12.5px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline" :href="gallery.url">{{ $t('galleryRecord.openLive') }}</a>
            </div>
          </div>
        </div>

        <!-- Einstellungen: Pixieset's General Settings shape — a
             sub-navigation in the rail, one labelled section at a time here. -->
        <div v-else-if="tab === 'einstellungen'" class="min-w-0 flex-1 px-8 pt-7 pb-8">
          <Settings :gallery="gallery" :siblings="siblings" :versions="versions" :lists="lists" :settings-section="settingsSection" />
        </div>

        <div v-else class="min-w-0 flex-1 px-8 pt-7 pb-8">
          <!-- Marked/downloaded/viewed: a look backwards, so they sit above
               the sheets that spell the same facts out row by row. -->
          <div class="mb-5 flex">
            <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
              <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.marked') }}</div>
              <div class="mt-[5px] text-[17px] leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums">{{ figures.marked }}</div>
            </div>
            <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
              <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.downloaded') }}</div>
              <div class="mt-[5px] text-[17px] leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums">{{ figures.downloaded }}</div>
            </div>
            <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
              <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.viewed') }}</div>
              <div class="mt-[5px] text-[17px] leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums">{{ figures.viewed }}</div>
            </div>
          </div>

          <ChoiceGroup
            v-model="sheet"
            :options="SHEETS.map((name) => ({ value: name, label: $t(`galleryRecord.sheet.${name}`) }))"
            variant="segmented"
          />

          <table class="mt-4 w-full border-collapse">
            <thead>
              <tr>
                <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.who') }}</th>
                <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.what') }}</th>
                <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2 text-right">{{ $t('galleryRecord.when') }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="sheet === 'downloads'">
                <tr v-for="(row, index) in activity.downloads" :key="index">
                  <td class="px-8 py-2.5 align-baseline text-[13.5px] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]">{{ row.who }}</td>
                  <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">{{ row.what ?? $t('gallery.everything') }} · {{ $t(`gallery.size.${row.size}`) }}</td>
                  <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums">{{ day(row.on) }}</td>
                </tr>
              </template>

              <template v-else-if="sheet === 'marks'">
                <tr v-for="(row, index) in activity.marks" :key="index">
                  <td class="px-8 py-2.5 align-baseline text-[13.5px] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                  <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">{{ row.what }}</td>
                  <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums">{{ day(row.on) }}</td>
                </tr>
                <tr v-for="(row, index) in activity.comments" :key="`c${index}`">
                  <td class="px-8 py-2.5 align-baseline text-[13.5px] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                  <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">{{ row.what }}</td>
                  <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums">{{ day(row.on) }}</td>
                </tr>
              </template>

              <template v-else>
                <tr v-for="(row, index) in activity.views" :key="index">
                  <td class="px-8 py-2.5 align-baseline text-[13.5px] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                  <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">—</td>
                  <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums">{{ day(row.on) }}</td>
                </tr>
              </template>
            </tbody>
          </table>

          <!-- Fertige ZIP-Archive: Tatsachen über Vergangenes, keine
               Einstellung — verlässt deshalb den ⚙-Reiter. -->
          <h2 class="mt-9">{{ $t('galleryRecord.downloadsHeading') }}</h2>
          <table v-if="builds.length" class="w-full border-collapse">
            <thead>
              <tr>
                <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('gallery.sizeLabel') }}</th>
                <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.parts') }}</th>
                <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2 text-right">{{ $t('galleryRecord.size') }}</th>
                <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.state') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="build in builds" :key="build.id">
                <td class="px-8 py-2.5 align-baseline text-[13.5px] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]">{{ $t(`gallery.size.${build.size}`) }}</td>
                <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">{{ build.parts }}</td>
                <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums">{{ megabytes(build.bytes) }}</td>
                <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">{{ build.status }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-[11.5px] leading-[1.45] text-faint">{{ $t('galleryRecord.noBuilds') }}</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
