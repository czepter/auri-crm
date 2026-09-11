<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useMediaQuery, useVirtualList } from '@vueuse/core'
import { applyClick, uploadTarget, type SelectionState } from '@/admin/lib/gallerySelection'
import { checkDuplicates, uploadFile } from '@/admin/lib/multipartUpload'
import AssetLightbox from '@/admin/components/gallery/AssetLightbox.vue'
import DropZone from '@/admin/components/gallery/DropZone.vue'
import DuplicateDialog from '@/admin/components/gallery/DuplicateDialog.vue'
import SetPickerDialog from '@/admin/components/gallery/SetPickerDialog.vue'
import UploadTray, { type TrayRow } from '@/admin/components/gallery/UploadTray.vue'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

interface AssetRow {
  id: string
  key: string
  setId: string
  filename: string
  src: string
  isCover: boolean
}

interface SetRow {
  id: string
  name: string
  count: number
  density: string
}

interface PendingUploadRow {
  id: string
  setId: string
  filename: string
  stage: 'transferring' | 'processing'
}

const props = defineProps<{
  gallery: { id: string }
  sets: SetRow[]
  assets: AssetRow[]
  activeSetId: string | null
  failedAssets: { publicId: string; filename: string; error: string }[]
  pendingUploads: PendingUploadRow[]
}>()

const { t } = useI18n()

const COLUMNS: Record<string, number> = { small: 10, medium: 7, large: 4 }
// Auf 390px sind vier Kacheln 85px breit — das ist kein Bild mehr, das ist
// ein Punkt. Unter 640px die Haelfte, mindestens zwei.
const narrow = useMediaQuery('(max-width: 640px)')

// gallery_grid_prefs is keyed per set, and Alle is not a set — the combined
// view renders at the default density rather than writing to a row that
// does not represent it (R21).
const activeSet = computed(() => props.sets.find((row) => row.id === props.activeSetId) ?? null)
const density = computed(() => activeSet.value?.density ?? 'medium')
const columns = computed(() => {
  const full = COLUMNS[density.value] ?? COLUMNS.medium

  return narrow.value ? Math.max(2, Math.floor(full / 2)) : full
})

// The backend already orders `assets` the way Gallery::draftAssets() would
// (sets by position, each set by its own rule) — filtering to one set keeps
// that order, and Alle needs no re-sort of its own.
const shown = computed(() =>
  props.activeSetId === null ? props.assets : props.assets.filter((asset) => asset.setId === props.activeSetId),
)

const setNames = computed(() => new Map(props.sets.map((row) => [row.id, row.name])))

const pendingShown = computed(() =>
  props.activeSetId === null ? props.pendingUploads : props.pendingUploads.filter((upload) => upload.setId === props.activeSetId),
)

const rows = computed(() => {
  const chunks: AssetRow[][] = []

  for (let index = 0; index < shown.value.length; index += columns.value) {
    chunks.push(shown.value.slice(index, index + columns.value))
  }

  return chunks
})

const { list, containerProps, wrapperProps } = useVirtualList(rows, { itemHeight: 132 })

const selection = ref<SelectionState>({ selected: new Set(), anchor: null })
const lightbox = ref<AssetRow | null>(null)

// A selection made against one set's rows has nothing to say about another
// set's — switching what is shown clears it rather than carrying ids the
// grid no longer displays.
watch(
  () => props.activeSetId,
  () => (selection.value = { selected: new Set(), anchor: null }),
)

function click(asset: AssetRow, event: MouseEvent) {
  selection.value = applyClick(
    selection.value,
    shown.value.map((row) => row.id),
    asset.id,
    { shift: event.shiftKey, meta: event.metaKey || event.ctrlKey },
  )
}

function moveTo(setId: string) {
  if (setId === '') return

  router.post(
    `/galerien/${props.gallery.id}/bilder/verschieben`,
    { asset_ids: [...selection.value.selected], gallery_set_id: setId },
    { preserveScroll: true, onSuccess: () => (selection.value = { selected: new Set(), anchor: null }) },
  )
}

// Genau eines: ein Titelbild ist eines, und aus zwei markierten Bildern
// eines zu waehlen waere geraten.
function setCover() {
  router.patch(
    `/galerien/${props.gallery.id}/titelbild`,
    { asset_id: [...selection.value.selected][0] },
    { preserveScroll: true, onSuccess: () => (selection.value = { selected: new Set(), anchor: null }) },
  )
}

function remove() {
  router.delete(`/galerien/${props.gallery.id}/bilder`, {
    data: { asset_ids: [...selection.value.selected] },
    preserveScroll: true,
    onSuccess: () => (selection.value = { selected: new Set(), anchor: null }),
  })
}

function setDensity(value: string) {
  if (activeSet.value === null) return

  router.patch(`/galerien/${props.gallery.id}/sets/${activeSet.value.id}/dichte`, { density: value }, { preserveScroll: true })
}

function step(by: number) {
  const index = shown.value.findIndex((row) => row.id === lightbox.value?.id)

  lightbox.value = shown.value[index + by] ?? lightbox.value
}

function retryFailed(publicId: string) {
  router.post(`/medien/${publicId}/wiederholen`, {}, { preserveScroll: true })
}

const tray = ref<TrayRow[]>([])
const pending = ref<File[]>([])
// Captured once, at pick time — the duplicate dialog can stay open across a
// set switch, and reading activeSet again once the photographer resolves it
// would chase a set that moved out from under the upload.
const pendingSetId = ref<string | null>(null)
const duplicates = ref<{ filename: string; size: number; assetId: string }[]>([])
const precheckError = ref(false)

function keyOf(file: File): string {
  return `${file.name}:${file.size}`
}

// Alle ist offen und die Galerie hat mehrere Sets: die Dateien warten hier,
// bis der Fotograf eines gewaehlt hat.
const awaitingSet = ref<File[]>([])
const dragging = ref(false)

async function picked(files: File[]) {
  if (files.length === 0) return

  const target = uploadTarget(props.sets, props.activeSetId)

  if (target === null) {
    awaitingSet.value = files
    return
  }

  await start(files, target)
}

function chooseSet(setId: string) {
  const files = awaitingSet.value

  awaitingSet.value = []
  void start(files, setId)
}

async function start(files: File[], setId: string) {
  pending.value = files
  pendingSetId.value = setId
  precheckError.value = false

  try {
    duplicates.value = await checkDuplicates(props.gallery.id, files.map((file) => ({ filename: file.name, size: file.size })))
  } catch {
    precheckError.value = true
    return
  }

  if (duplicates.value.length === 0) {
    await run(files, new Map())
  }
}

async function run(files: File[], replacements: Map<string, string>) {
  const targetSetId = pendingSetId.value
  if (targetSetId === null) return

  tray.value = files.map((file) => ({ key: keyOf(file), name: file.name, size: file.size, status: 'queued', percent: 0 }))

  for (const file of files) {
    const row = tray.value.find((entry) => entry.key === keyOf(file))!
    row.status = 'uploading'

    try {
      const result = await uploadFile(
        `/galerien/${props.gallery.id}/uploads`,
        file,
        ({ partsDone, partsTotal }) => (row.percent = partsTotal === 0 ? 0 : Math.round((partsDone / partsTotal) * 100)),
        targetSetId,
        replacements.get(keyOf(file)),
      )
      row.status = 'done'

      // A resumed session keeps the set it was started against — surface it
      // rather than let the file quietly land somewhere the photographer
      // never asked to look.
      if (result.gallerySetId !== targetSetId) {
        row.redirectedTo = setNames.value.get(result.gallerySetId) ?? result.gallerySetId
      }
    } catch {
      row.status = 'error'
    }
  }

  router.reload({ only: ['assets', 'sets'] })
}

function skipDuplicates() {
  const names = new Set(duplicates.value.map((row) => `${row.filename}:${row.size}`))

  duplicates.value = []
  void run(pending.value.filter((file) => !names.has(keyOf(file))), new Map())
}

function replaceDuplicates() {
  const map = new Map(duplicates.value.map((row) => [`${row.filename}:${row.size}`, row.assetId]))

  duplicates.value = []
  void run(pending.value, map)
}

const transferring = computed(() => tray.value.some((row) => row.status === 'uploading' || row.status === 'queued'))

function guardUnload(event: BeforeUnloadEvent) {
  event.preventDefault()
}

watch(transferring, (on) => (on ? window.addEventListener('beforeunload', guardUnload) : window.removeEventListener('beforeunload', guardUnload)))

// beforeunload only catches the hard exit. Inertia swaps the page without
// reloading, and the running transfer dies with this component.
const stopInertiaGuard = router.on('before', () => (transferring.value ? window.confirm(t('media.leaveWhileUploading')) : true))

onUnmounted(() => {
  window.removeEventListener('beforeunload', guardUnload)
  stopInertiaGuard()
})
</script>

<template>
  <div class="relative px-6 pt-5 pb-7" @dragover.prevent="dragging = true">
    <!-- Die ganze Flaeche nimmt Bilder an, nicht nur der Kasten am Ende —
         das Overlay faengt dragleave einmal ab statt bei jeder Kachel. -->
    <div
      v-if="dragging"
      class="absolute inset-0 z-40 flex items-center justify-center rounded-lg border-2 border-dashed border-action bg-background/90 text-[14px] font-semibold"
      @dragover.prevent
      @dragleave="dragging = false"
      @drop.prevent="dragging = false; picked(Array.from($event.dataTransfer?.files ?? []))"
    >
      {{ $t('media.dropHere') }}
    </div>

    <div class="mb-3 flex items-center gap-3">
      <h2 class="m-0 text-[17px] leading-[1.2] font-semibold tracking-[-0.022em]">
        {{ activeSet === null ? $t('gallery.all') : activeSet.name }}
      </h2>
      <span class="text-[12px] text-ink-2">{{ shown.length }}</span>
      <span v-if="pendingShown.length > 0" class="text-[12px] text-ink-2">{{ $t('media.pendingCount', pendingShown.length) }}</span>
      <div class="ml-auto flex items-center gap-3 text-[11.5px] text-ink-2">
        <template v-if="activeSet !== null">
          <button
            v-for="option in ['small', 'medium', 'large']"
            :key="option"
            class="cursor-pointer border-0 bg-transparent text-[11.5px]"
            :class="density === option ? 'font-semibold text-foreground' : 'text-ink-2'"
            type="button"
            @click="setDensity(option)"
          >
            {{ $t(`galleryRecord.density.${option}`) }}
          </button>
        </template>
        <label class="cursor-pointer">
          {{ $t('media.upload') }}
          <Input type="file" multiple class="hidden" @change="picked(Array.from(($event.target as HTMLInputElement).files ?? []))" />
        </label>
      </div>
    </div>

    <!-- An empty set has nothing to scroll — the reserved grid height would
         otherwise sit above the drop zone as a void, the only thing left to
         act on. A set that is empty of pictures but not of pending uploads
         is not this case — there is something to show. -->
    <DropZone v-if="shown.length === 0 && pendingShown.length === 0" class="h-[calc(100vh-137px)]" @files="picked" />

    <template v-else>
      <!-- Bytes G-03 has not turned into a picture yet — sits above the real
           grid rather than inside its virtualized rows, since there are only
           ever a handful of these at once. -->
      <div
        v-if="pendingShown.length > 0"
        class="mb-1.5 grid gap-1.5"
        :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }"
      >
        <div
          v-for="upload in pendingShown"
          :key="upload.id"
          class="flex aspect-square w-full flex-col items-center justify-center gap-1 rounded-md bg-muted p-1 text-center"
        >
          <span class="text-[8.5px] text-muted-foreground">{{ $t(`media.${upload.stage}`) }}</span>
          <span class="line-clamp-2 text-[8.5px] text-faint">{{ upload.filename }}</span>
        </div>
      </div>

      <!-- 137px = Seitenkopf ueber der Flaeche (109) plus pb-7 darunter. Die
           Zahl hielt frueher zusaetzlich den Kasten am Ende frei; den gibt es
           nicht mehr, also endet das Raster wieder am Fensterrand. -->
      <div v-if="shown.length > 0" v-bind="containerProps" class="h-[calc(100vh-137px)]">
        <div v-bind="wrapperProps">
          <div
            v-for="row in list"
            :key="row.index"
            class="mb-1.5 grid gap-1.5"
            :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }"
          >
            <figure
              v-for="asset in row.data"
              :key="asset.id"
              class="relative m-0 cursor-pointer"
              @click="click(asset, $event)"
              @dblclick="lightbox = asset"
            >
              <img
                class="block aspect-square w-full rounded-md object-cover"
                :class="selection.selected.has(asset.id) && 'outline-2 -outline-offset-2 outline-foreground'"
                :src="`${asset.src}?w=400&fmt=jpeg`"
                alt=""
                loading="lazy"
              />
              <span v-if="asset.isCover" class="absolute bottom-1.5 left-1.5 rounded bg-[rgba(25,25,25,0.7)] px-1.5 py-0.5 text-[8.5px] text-paper">
                {{ $t('galleryRecord.cover') }}
              </span>
              <span
                v-if="activeSetId === null"
                class="absolute top-1.5 left-1.5 rounded-full bg-[rgba(25,25,25,0.7)] px-1.5 py-0.5 text-[8.5px] text-paper"
              >
                {{ setNames.get(asset.setId) }}
              </span>
            </figure>
          </div>
        </div>
      </div>
    </template>

    <p v-if="precheckError" class="mt-2 text-[11.5px] text-destructive">{{ $t('media.precheckFailed') }}</p>

    <div v-if="failedAssets.length > 0" class="mt-6">
      <div class="mb-2 text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">
        {{ $t('media.needsAttention') }} · {{ $t('media.needsAttentionCount', failedAssets.length) }}
      </div>
      <div class="overflow-hidden rounded-md border border-border">
        <div v-for="asset in failedAssets" :key="asset.publicId" class="flex items-center gap-2.5 border-t border-border p-2.5 text-[12.5px] first:border-t-0">
          <span class="font-medium text-foreground">{{ asset.filename }}</span>
          <span class="text-ink-2">{{ asset.error }}</span>
          <button class="ml-auto cursor-pointer border-0 bg-transparent text-[12.5px] text-faint" type="button" @click="retryFailed(asset.publicId)">
            {{ $t('media.retryProcessing') }}
          </button>
        </div>
      </div>
    </div>

    <UploadTray :rows="tray" />
    <SetPickerDialog :open="awaitingSet.length > 0" :sets="sets" @choose="chooseSet" @cancel="awaitingSet = []" />
    <DuplicateDialog :open="duplicates.length > 0" :duplicates="duplicates" @skip="skipDuplicates" @replace="replaceDuplicates" />

    <div
      v-if="selection.selected.size > 0"
      class="fixed bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-4 rounded-full bg-foreground px-5 py-2.5 text-[12.5px] text-paper"
    >
      <b>{{ $t('galleryRecord.selected', selection.selected.size) }}</b>
      <!-- Die Auswahl ist eine Aktion, kein Zustand: der Wert bleibt leer,
           damit der Auslöser nach dem Verschieben wieder sein Label zeigt. -->
      <Select :model-value="undefined" @update:model-value="(value) => moveTo(String(value))">
        <SelectTrigger class="h-7 w-auto border-0 bg-transparent py-0 text-paper data-[placeholder]:text-paper">
          <SelectValue :placeholder="$t('galleryRecord.moveTo')" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="row in sets" :key="row.id" :value="row.id">{{ row.name }}</SelectItem>
        </SelectContent>
      </Select>
      <button
        v-if="selection.selected.size === 1"
        class="cursor-pointer border-0 bg-transparent text-paper"
        type="button"
        @click="setCover"
      >
        {{ $t('galleryRecord.setCover') }}
      </button>
      <button class="cursor-pointer border-0 bg-transparent text-paper" type="button" @click="remove">
        {{ $t('common.remove') }}
      </button>
    </div>

    <AssetLightbox
      v-if="lightbox"
      :src="lightbox.src"
      :filename="lightbox.filename"
      @close="lightbox = null"
      @prev="step(-1)"
      @next="step(1)"
    />
  </div>
</template>
