<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
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
    // No set picker on this screen yet — a later task replaces it with one
    // that names a real target; '' only keeps this compiling until then.
    await uploadFile(`/galerien/${props.target.galleryId}/uploads`, entry.file, ({ partsDone: done, partsTotal: total }) => {
      entry.percent = total === 0 ? 0 : Math.round((done / total) * 100)
    }, '')
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
    <div data-tokens>
      <PageHead :kicker="$t('media.kicker')" :title="target.galleryTitle">
        <Button v-if="target.projectId" as="a" class="ml-auto" variant="ghost" :href="`/auftraege/${target.projectId}`">
          {{ $t('media.backToProject') }}
        </Button>
        <!-- the file input stays hidden and the label is the button, which is
             the only way to style a file picker at all -->
        <Button as="label" class="cursor-pointer" variant="pill">
          {{ $t('media.pickFiles') }}
          <Input type="file" multiple class="hidden" @change="onFilesPicked" />
        </Button>
      </PageHead>

      <div class="px-8 pt-7 pb-8">

        <div class="mt-2.5 flex flex-row gap-6 [&_span]:inline-flex [&_span]:items-center [&_span]:bg-[length:13px_13px] [&_span]:bg-[position:0_center] [&_span]:bg-no-repeat [&_span]:pl-[19px] [&_span]:text-[12.5px] [&_span]:leading-[1.35] [&_span]:font-normal [&_span]:text-muted-foreground">
          <span v-if="target.shootLabel" class="bg-icon-date">{{ target.shootLabel }}</span>
          <span class="bg-icon-venue">{{ target.galleryTitle }}</span>
        </div>

        <div v-if="fileList.length > 0" class="relative block cursor-default rounded-md border border-border p-2.5 text-inherit no-underline mt-6">
          <div class="mb-4 flex items-center justify-between">
            <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t(transferring ? 'media.transferring' : 'media.filesLabel') }}</span>
            <span class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ overallPercent }} %</span>
          </div>
          <span class="block h-1.5 overflow-hidden rounded-[2px] bg-muted [&>i]:block [&>i]:h-full [&>i]:rounded-[2px] [&>i]:bg-foreground"><i :style="{ width: `${overallPercent}%` }" /></span>
          <div class="text-[11.5px] leading-[1.45] font-normal text-faint mt-4">{{ $t('media.resumeHint') }}</div>
        </div>

        <!-- G-04: the needs-attention tray — Failed assets from this gallery's own
             ingest history, never just the current transfer's own client-side
             errors above. -->
        <div v-if="failedAssets.length > 0" class="mt-8 first-of-type:mt-6">
          <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('media.needsAttention') }} · {{ $t('media.needsAttentionCount', failedAssets.length) }}</div>
          <div class="overflow-hidden rounded-md border border-border">
            <div v-for="asset in failedAssets" :key="asset.publicId" class="flex items-center gap-2.5 border-t border-border p-2.5 text-inherit no-underline transition-[background-color] duration-[.12s] ease-out first:border-t-0 hover:bg-muted">
              <span class="text-[13px] leading-[1.3] font-medium text-foreground">{{ asset.filename }}</span>
              <span class="text-[12.5px] leading-[1.3] font-normal text-muted-foreground">{{ asset.error }}</span>
              <a class="ml-auto text-[12.5px] leading-none font-medium text-faint" href="#" @click.prevent="retryFailedAsset(asset)">{{ $t('media.retryProcessing') }}</a>
            </div>
          </div>
        </div>

        <div class="mt-8 grid grid-cols-[minmax(0,1fr)_296px] items-start gap-8">
          <div class="flex flex-col gap-6">
            <div class="relative block cursor-default rounded-md border border-border p-2.5 text-inherit no-underline">
              <div class="mb-4 flex items-center justify-between">
                <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('media.filesLabel') }}</span>
                <span class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-muted text-muted-foreground">{{ partsDone }} {{ $t('media.of') }} {{ partsTotal }}</span>
              </div>

              <p v-if="fileList.length === 0" class="text-[11.5px] leading-[1.45] font-normal text-faint">
                {{ $t('media.empty') }}
              </p>

              <div v-else class="flex flex-col">
                <div v-for="entry in fileList" :key="keyOf(entry.file)" class="grid grid-cols-[minmax(0,1fr)_84px_76px] items-center gap-x-2.5 gap-y-[7px] py-[7px]"
                  :class="entry.status === 'queued' && '[&>span:first-child]:font-normal [&>span:first-child]:text-muted-foreground'">
                  <span class="min-w-0 overflow-hidden text-[12.5px] leading-[1.3] font-medium text-ellipsis whitespace-nowrap">{{ entry.file.name }}</span>
                  <span class="text-right text-[12px] leading-none font-normal tabular-nums text-faint">{{ formatSize(entry.file.size) }}</span>

                  <span v-if="entry.status === 'done'" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase justify-self-end bg-ok-bg text-ok">{{ $t('media.ready') }}</span>
                  <span v-else-if="entry.status === 'error'" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase justify-self-end bg-late-bg text-destructive">{{ $t('media.failed') }}</span>
                  <span v-else-if="entry.status === 'uploading'" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase justify-self-end bg-due-bg text-due">{{ entry.percent }} %</span>
                  <span v-else class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase justify-self-end bg-muted text-muted-foreground">{{ $t('media.queued') }}</span>

                  <span v-if="entry.status === 'uploading'" class="col-start-1 col-end-3 block h-[3px] overflow-hidden rounded-[2px] bg-muted [&>i]:block [&>i]:h-full [&>i]:rounded-[2px] [&>i]:bg-foreground"><i :style="{ width: `${entry.percent}%` }" /></span>
                  <a v-if="entry.status === 'error'" class="ml-auto text-[12.5px] leading-none font-medium text-faint" href="#" @click.prevent="retry(entry)">{{ $t('media.retry') }}</a>
                </div>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-6">
            <div class="relative block cursor-default rounded-md border border-border p-2.5 text-inherit no-underline">
              <div class="mb-4 flex items-center justify-between"><span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('media.target') }}</span></div>
              <div class="grid grid-cols-[repeat(3,minmax(0,1fr))] gap-2.5">
                <div v-if="target.projectName" class="rounded-md bg-muted px-4 pt-3.5 pb-4"><div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('media.project') }}</div><div class="mt-[9px] text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ target.projectName }}</div></div>
                <div v-if="target.shootLabel" class="rounded-md bg-muted px-4 pt-3.5 pb-4"><div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('media.shoot') }}</div><div class="mt-[9px] text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ target.shootLabel }}</div></div>
                <div class="rounded-md bg-muted px-4 pt-3.5 pb-4"><div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('media.gallery') }}</div><div class="mt-[9px] text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ target.galleryTitle }}</div></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
