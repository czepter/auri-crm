<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { uploadFile } from '@/admin/lib/multipartUpload'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'

const props = defineProps<{
  target: App.Media.Data.MediaIngestTargetData
  failedAssets: App.Media.Data.FailedAssetData[]
}>()

type FileState = { file: File; status: 'queued' | 'uploading' | 'done' | 'error'; percent: number }

// Keyed by name+size+lastModified rather than array index — index shifts
// meaning the moment a second file input selection is merged in, which
// would silently reattach a running upload's progress to the wrong row.
const files = reactive(new Map<string, FileState>())

function keyOf(file: File): string {
  return `${file.name}:${file.size}:${file.lastModified}`
}

const fileList = computed(() => [...files.values()])
const partsTotal = computed(() => fileList.value.length)
const partsDone = computed(() => fileList.value.filter((entry) => entry.status === 'done').length)
const overallPercent = computed(() =>
  partsTotal.value === 0 ? 0 : Math.round((partsDone.value / partsTotal.value) * 100),
)
const transferring = computed(() => fileList.value.some((entry) => entry.status === 'uploading' || entry.status === 'queued'))

async function runUpload(entry: FileState) {
  entry.status = 'uploading'

  try {
    await uploadFile(`/galerien/${props.target.galleryId}/uploads`, entry.file, ({ partsDone: done, partsTotal: total }) => {
      entry.percent = total === 0 ? 0 : Math.round((done / total) * 100)
    })
    entry.status = 'done'
  } catch {
    entry.status = 'error'
  }
}

async function onFilesPicked(event: Event) {
  const picked = Array.from((event.target as HTMLInputElement).files ?? [])

  for (const file of picked) {
    const key = keyOf(file)

    // Re-picking the same file (a reload, or "denselben Ordner" after a
    // dropped connection) resumes rather than restarts — uploadFile's own
    // startOrResumeUpload asks the bucket what already landed. A file
    // already marked done here just gets skipped outright.
    if (files.has(key) && files.get(key)!.status === 'done') {
      continue
    }

    files.set(key, { file, status: 'queued', percent: 0 })
  }

  for (const file of picked) {
    const entry = files.get(keyOf(file))!

    if (entry.status !== 'done') {
      await runUpload(entry)
    }
  }
}

function retry(entry: FileState) {
  void runUpload(entry)
}

// G-04: re-runs G-03's ingest pipeline for one Failed asset — distinct from
// retry() above, which resumes a still-open client-side transfer.
function retryFailedAsset(asset: App.Media.Data.FailedAssetData) {
  router.post(`/medien/${asset.publicId}/wiederholen`, {}, { preserveScroll: true })
}

function formatSize(bytes: number): string {
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
  <AdminLayout active="">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('media.kicker') }}</div>
        <h1>{{ target.galleryTitle }}</h1>
      </div>
      <Button as="a" variant="ghost" :href="`/auftraege/${target.projectId}`">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6" /></svg>
        {{ $t('media.backToProject') }}
      </Button>
      <!-- the file input stays hidden and the label is the button, which is
           the only way to style a file picker at all -->
      <Button as="label" class="cursor-pointer">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('media.pickFiles') }}
        <Input type="file" multiple class="hidden" @change="onFilesPicked" />
      </Button>
    </div>

    <div class="meta row">
      <span v-if="target.shootLabel" class="d">{{ target.shootLabel }}</span>
      <span class="v">{{ target.galleryTitle }}</span>
    </div>

    <div v-if="fileList.length > 0" class="card static" style="margin: var(--s5) 0 0">
      <div class="cardhead">
        <span class="kick">{{ $t(transferring ? 'media.transferring' : 'media.filesLabel') }}</span>
        <span class="chip now">{{ overallPercent }} %</span>
      </div>
      <span class="bar big"><i :style="{ width: `${overallPercent}%` }" /></span>
      <div class="hint" style="margin-top: var(--s4)">{{ $t('media.resumeHint') }}</div>
    </div>

    <!-- G-04: the needs-attention tray — Failed assets from this gallery's own
         ingest history, never just the current transfer's own client-side
         errors above. -->
    <div v-if="failedAssets.length > 0" class="dsec">
      <div class="dlab warn">{{ $t('media.needsAttention') }} · {{ $t('media.needsAttentionCount', failedAssets.length) }}</div>
      <div class="panel">
        <div v-for="asset in failedAssets" :key="asset.publicId" class="trow">
          <span class="tfile">{{ asset.filename }}</span>
          <span class="treason">{{ asset.error }}</span>
          <a class="tretry" href="#" @click.prevent="retryFailedAsset(asset)">{{ $t('media.retryProcessing') }}</a>
        </div>
      </div>
    </div>

    <div class="split" style="margin-top: var(--s6)">
      <div class="stackcol">
        <div class="card static">
          <div class="cardhead">
            <span class="kick">{{ $t('media.filesLabel') }}</span>
            <span class="chip next">{{ partsDone }} {{ $t('media.of') }} {{ partsTotal }}</span>
          </div>

          <p v-if="fileList.length === 0" class="hint">{{ $t('media.empty') }}</p>

          <div v-else class="flist">
            <div v-for="entry in fileList" :key="keyOf(entry.file)" class="frow" :class="{ queued: entry.status === 'queued' }">
              <span class="fname">{{ entry.file.name }}</span>
              <span class="fsize">{{ formatSize(entry.file.size) }}</span>

              <span v-if="entry.status === 'done'" class="chip done">{{ $t('media.ready') }}</span>
              <span v-else-if="entry.status === 'error'" class="chip fail">{{ $t('media.failed') }}</span>
              <span v-else-if="entry.status === 'uploading'" class="chip now">{{ entry.percent }} %</span>
              <span v-else class="chip next">{{ $t('media.queued') }}</span>

              <span v-if="entry.status === 'uploading'" class="bar"><i :style="{ width: `${entry.percent}%` }" /></span>
              <a v-if="entry.status === 'error'" class="tretry" href="#" @click.prevent="retry(entry)">{{ $t('media.retry') }}</a>
            </div>
          </div>
        </div>
      </div>

      <div class="stackcol">
        <div class="card static">
          <div class="cardhead"><span class="kick">{{ $t('media.target') }}</span></div>
          <div class="tiles">
            <div class="fact"><div class="l">{{ $t('media.project') }}</div><div class="v">{{ target.projectName }}</div></div>
            <div v-if="target.shootLabel" class="fact"><div class="l">{{ $t('media.shoot') }}</div><div class="v">{{ target.shootLabel }}</div></div>
            <div class="fact"><div class="l">{{ $t('media.gallery') }}</div><div class="v">{{ target.galleryTitle }}</div></div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
