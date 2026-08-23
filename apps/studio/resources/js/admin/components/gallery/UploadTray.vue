<script setup lang="ts">
import { computed } from 'vue'

export interface TrayRow {
  key: string
  name: string
  size: number
  status: 'queued' | 'uploading' | 'done' | 'error'
  percent: number
  /** Set only when a resumed session landed in a different set than the one open — see multipartUpload.ts's own doc comment. */
  redirectedTo?: string
}

const props = defineProps<{ rows: TrayRow[] }>()

const done = computed(() => props.rows.filter((row) => row.status === 'done').length)
const percent = computed(() => (props.rows.length === 0 ? 0 : Math.round((done.value / props.rows.length) * 100)))

// TrayRow's own status names (done/error) aren't the words shown — those are
// media.ready and media.failed, the same pair the old ingest screen used.
const STATUS_KEY: Record<TrayRow['status'], string> = {
  queued: 'media.queued',
  uploading: '',
  done: 'media.ready',
  error: 'media.failed',
}
</script>

<template>
  <div v-if="rows.length > 0" class="fixed right-5 bottom-5 w-[260px] overflow-hidden rounded-lg border border-border bg-paper shadow-lg">
    <div class="border-b border-border px-3 py-2.5">
      <b class="text-[12px] font-semibold">{{ $t('media.filesLabel') }}</b>
      <span class="mt-0.5 block text-[11px] tabular-nums text-ink-2">{{ done }} / {{ rows.length }} · {{ percent }} %</span>
    </div>
    <span class="block h-[3px] bg-muted"><i class="block h-full bg-foreground" :style="{ width: `${percent}%` }" /></span>
    <div v-for="row in rows.slice(0, 4)" :key="row.key" class="flex items-center gap-2 border-t border-border px-3 py-1.5 text-[11px]">
      <span class="min-w-0 flex-1 truncate">{{ row.name }}</span>
      <span v-if="row.redirectedTo" class="text-ink-2">{{ $t('media.landedIn', { set: row.redirectedTo }) }}</span>
      <span class="tabular-nums text-ink-2">{{ row.status === 'uploading' ? `${row.percent} %` : $t(STATUS_KEY[row.status]) }}</span>
    </div>
  </div>
</template>
