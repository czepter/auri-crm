<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * C-03 (#237): the search is a tool in the sidebar, not a field in a header.
 *
 * A native <dialog> opened with showModal(). The focus trap, Esc and the
 * inert background all come from the browser; the only key this listens for
 * is ⌘K, because that one has no native equivalent.
 *
 * It opens on what one has just been looking at rather than on an empty mask,
 * mixed in visit order — grouped by kind it would be sorted by something
 * nobody is searching for. Search over the stock is not part of this: the
 * shell and the history are.
 */
type Recent = { kind: string; title: string; href: string; at: string }

const dialog = ref<HTMLDialogElement | null>(null)
const recent = () => (usePage().props.recent as Recent[] | null) ?? []

function onKey(event: KeyboardEvent) {
  if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
    event.preventDefault()
    dialog.value?.showModal()
  }
}

onMounted(() => addEventListener('keydown', onKey))
onUnmounted(() => removeEventListener('keydown', onKey))

defineExpose({ open: () => dialog.value?.showModal() })

const relative = new Intl.RelativeTimeFormat('de-DE', { numeric: 'auto' })

// Minutes, then hours, then days. Nothing older than a week is in the list
// long enough for a finer unit to matter.
function when(at: string) {
  // At least one: Intl renders a zero as "in dieser Minute", which reads as
  // the future for something that has already happened.
  const minutes = Math.max(1, Math.round((Date.now() - new Date(at).getTime()) / 60000))

  if (minutes < 60) {
    return relative.format(-minutes, 'minute')
  }

  return minutes < 1440 ? relative.format(-Math.round(minutes / 60), 'hour') : relative.format(-Math.round(minutes / 1440), 'day')
}
</script>

<template>
  <dialog ref="dialog" class="palette" data-theme="crm">
    <input class="q" type="search" :placeholder="$t('palette.placeholder')" autofocus />

    <div class="results">
      <div class="lab">{{ $t('palette.recent') }}</div>
      <a v-for="entry in recent()" :key="entry.href" :href="entry.href">
        <span class="kind">{{ $t(`palette.kind.${entry.kind}`) }}</span>
        <span class="n">{{ entry.title }}</span>
        <span class="m">{{ when(entry.at) }}</span>
      </a>
    </div>
  </dialog>
</template>
