<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Switch } from '@/ui/switch'

const props = defineProps<{ views: App.Documents.Data.DocumentViewData[] }>()

const groups = computed(() => {
  const byKey = new Map<string, { key: string; ids: string[]; last: string; via: string; device: string; network: string | null; counts: boolean }>()

  for (const view of props.views) {
    const key = `${view.via}|${view.device}|${view.network}`
    const group = byKey.get(key)

    if (!group) {
      byKey.set(key, { key, ids: [view.id], last: view.viewedAt, via: view.via, device: view.device, network: view.network, counts: view.counts })
      continue
    }

    group.ids.push(view.id)
    group.counts = group.counts && view.counts
    if (view.viewedAt > group.last) group.last = view.viewedAt
  }

  return [...byKey.values()].sort((a, b) => (a.last < b.last ? 1 : -1))
})

function when(iso: string) {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function toggle(ids: string[], counts: boolean) {
  for (const id of ids) router.patch(`/oeffnungen/${id}`, { counts }, { preserveScroll: true })
}
</script>

<template>
  <ul class="m-0 list-none p-0">
    <li v-for="group in groups" :key="group.key" class="flex items-center gap-3 border-b border-border py-2 text-small last:border-b-0">
      <span class="whitespace-nowrap font-medium">{{ when(group.last) }}</span>
      <span v-if="group.ids.length > 1" class="tabular-nums">{{ group.ids.length }}×</span>
      <span class="text-ink-2">{{ $t(`documentViews.${group.via}`) }}</span>
      <span v-if="group.device">{{ group.device }}</span>
      <span v-if="group.network" class="text-ink-2">{{ $t('documentViews.network', { network: group.network }) }}</span>
      <label class="ml-auto flex items-center gap-2">
        {{ $t('documentViews.counts') }}
        <Switch :model-value="group.counts" @update:model-value="toggle(group.ids, $event)" />
      </label>
    </li>
  </ul>
</template>
