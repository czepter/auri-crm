<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Switch } from '@/ui/switch'

defineProps<{ views: App.Documents.Data.DocumentViewData[] }>()

function when(iso: string) {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function toggle(id: string, counts: boolean) {
  router.patch(`/oeffnungen/${id}`, { counts }, { preserveScroll: true })
}
</script>

<template>
  <ul class="m-0 list-none p-0">
    <li v-for="view in views" :key="view.id" class="flex items-center gap-3 border-b border-border py-2 text-small last:border-b-0">
      <span class="whitespace-nowrap font-medium">{{ when(view.viewedAt) }}</span>
      <span class="text-ink-2">{{ $t(`documentViews.${view.via}`) }}</span>
      <span v-if="view.device">{{ view.device }}</span>
      <span v-if="view.network" class="text-ink-2">{{ $t('documentViews.network', { network: view.network }) }}</span>
      <label class="ml-auto flex items-center gap-2">
        {{ $t('documentViews.counts') }}
        <Switch :model-value="view.counts" @update:model-value="toggle(view.id, $event)" />
      </label>
    </li>
  </ul>
</template>
