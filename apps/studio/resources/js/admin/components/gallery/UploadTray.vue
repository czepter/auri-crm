<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { Maximize2, Minimize2 } from 'lucide-vue-next'
import { Button } from '@/ui/button'

export interface TrayRow {
  key: string
  file: File
  name: string
  size: number
  loaded: number
  status: 'queued' | 'uploading' | 'done' | 'error'
  percent: number
  /** Set only when a resumed session landed in a different set than the one open — see multipartUpload.ts's own doc comment. */
  redirectedTo?: string
}

const props = defineProps<{ rows: TrayRow[] }>()

const expanded = ref(false)

const total = computed(() => props.rows.reduce((sum, row) => sum + row.size, 0))
const uploaded = computed(() => props.rows.reduce((sum, row) => sum + (row.status === 'done' ? row.size : row.loaded), 0))
const transferring = computed(() => props.rows.some((row) => row.status === 'uploading' || row.status === 'queued'))

// Active rows first, finished ones sink; sort is stable so upload order holds within a group.
const sorted = computed(() => [...props.rows].sort((a, b) => Number(a.status === 'done') - Number(b.status === 'done')))
const visible = computed(() => (expanded.value ? sorted.value : sorted.value.slice(0, 4)))

// ponytail: bytes arrive per part (fetch has no upload progress), so speed is a 8 s sliding window.
const WINDOW_MS = 8000
let samples: { at: number; bytes: number }[] = []
const speed = ref(0)

watch(
  () => props.rows,
  () => {
    samples = []
    speed.value = 0
    revokePreviews()
  },
)

watch(uploaded, (bytes) => {
  const now = Date.now()
  samples = [...samples.filter((sample) => now - sample.at <= WINDOW_MS), { at: now, bytes }]

  const first = samples[0]
  speed.value = samples.length > 1 && now > first.at ? ((bytes - first.bytes) / (now - first.at)) * 1000 : speed.value
})

watch(transferring, (on) => {
  if (!on) speed.value = 0
})

const previews = new Map<string, string>()

function previewOf(row: TrayRow): string {
  let url = previews.get(row.key)
  if (!url) {
    url = URL.createObjectURL(row.file)
    previews.set(row.key, url)
  }

  return url
}

function revokePreviews() {
  previews.forEach((url) => URL.revokeObjectURL(url))
  previews.clear()
}

onUnmounted(revokePreviews)

function bytes(value: number): string {
  const units = ['byte', 'kilobyte', 'megabyte', 'gigabyte', 'terabyte']
  const index = value < 1 ? 0 : Math.min(units.length - 1, Math.floor(Math.log10(value) / 3))

  return new Intl.NumberFormat('de-DE', { style: 'unit', unit: units[index], maximumFractionDigits: index < 2 ? 0 : 1 }).format(value / 1000 ** index)
}

const done = computed(() => props.rows.filter((row) => row.status === 'done').length)
const percent = computed(() => (props.rows.length === 0 ? 0 : Math.round((done.value / props.rows.length) * 100)))

// TrayRow's own status names (done/error) aren't the words shown — those are
// media.ready and media.failed, the same pair the old ingest screen used.
// 'uploading' never reaches this map — the template shows a live percent
// for it instead, before ever consulting STATUS_KEY.
const STATUS_KEY: Record<Exclude<TrayRow['status'], 'uploading'>, string> = {
  queued: 'media.queued',
  done: 'media.ready',
  error: 'media.failed',
}
</script>

<template>
  <div
    v-if="rows.length > 0"
    class="fixed right-5 bottom-5 flex max-h-[calc(100vh-2.5rem)] flex-col overflow-hidden rounded-lg border border-border bg-paper shadow-lg"
    :class="expanded ? 'w-[min(560px,calc(100vw-2.5rem))]' : 'w-[260px]'"
  >
    <div class="flex items-start gap-2 border-b border-border px-3 py-2.5">
      <div class="min-w-0 flex-1">
        <b class="text-small font-semibold">{{ $t('media.filesLabel') }}</b>
        <span class="mt-0.5 block text-small tabular-nums text-ink-2">{{ done }} / {{ rows.length }} · {{ percent }} %</span>
        <span class="mt-0.5 block text-small tabular-nums text-ink-2">
          {{ bytes(uploaded) }} / {{ bytes(total) }}<template v-if="transferring && speed > 0"> · {{ bytes(speed) }}/s</template>
        </span>
      </div>
      <Button
        type="button"
        variant="ghost"
        size="icon"
        :aria-label="$t(expanded ? 'media.collapse' : 'media.expand')"
        @click="expanded = !expanded"
      >
        <component :is="expanded ? Minimize2 : Maximize2" />
      </Button>
    </div>
    <span class="block h-[3px] shrink-0 bg-muted"><i class="block h-full bg-foreground" :style="{ width: `${percent}%` }" /></span>
    <div class="min-h-0 overflow-y-auto">
      <div
        v-for="row in visible"
        :key="row.key"
        class="flex items-center gap-2 border-t border-border px-3 text-small"
        :class="row.status === 'done' ? 'py-1 text-ink-2' : 'py-1.5'"
      >
        <span class="shrink-0 overflow-hidden rounded-sm bg-muted" :class="row.status === 'done' ? 'size-5' : expanded ? 'size-12' : 'size-8'">
          <img
            :src="previewOf(row)"
            alt=""
            loading="lazy"
            decoding="async"
            class="size-full object-cover"
            @error="($event.target as HTMLElement).style.display = 'none'"
          />
        </span>
        <span class="min-w-0 flex-1 truncate">{{ row.name }}</span>
        <span v-if="row.redirectedTo" class="text-ink-2">{{ $t('media.landedIn', { set: row.redirectedTo }) }}</span>
        <span v-if="expanded" class="tabular-nums text-ink-2">{{ bytes(row.size) }}</span>
        <span class="tabular-nums text-ink-2">{{ row.status === 'uploading' ? `${row.percent} %` : $t(STATUS_KEY[row.status]) }}</span>
      </div>
    </div>
  </div>
</template>
