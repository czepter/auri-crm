<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Input } from '@/ui/input'

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
  <!-- No data-tokens of its own: the dialog is a DOM descendant of the
       layout's token root, and a modal in the top layer still inherits from
       where it stands in the tree. -->
  <dialog
    ref="dialog"
    class="mx-auto mt-[12vh] mb-auto w-[min(560px,calc(100vw-48px))] rounded-md border-0 bg-paper p-0 text-foreground backdrop:bg-[rgba(29,29,31,0.28)] backdrop:backdrop-blur-[3px]"
  >
    <!-- upper third: the list grows downward, the eye stays -->
    <Input
      class="rounded-none border-0 bg-transparent px-5.5 py-4.5 text-[20px] leading-[1.3] tracking-[-0.022em] shadow-[inset_0_-1px_0_var(--rule)] placeholder:text-[#B0B0B5]"
      type="search"
      :placeholder="$t('palette.placeholder')"
      autofocus
    />

    <div class="max-h-[52vh] overflow-auto pt-1.5 pb-2">
      <div class="px-5.5 pt-3 pb-1 text-[10.5px] tracking-[0.07em] uppercase text-ink-2">
        {{ $t('palette.recent') }}
      </div>
      <a
        v-for="entry in recent()"
        :key="entry.href"
        class="flex items-baseline gap-3 px-5.5 py-2 text-foreground no-underline hover:bg-sunk focus-visible:bg-sunk focus-visible:outline-0"
        :href="entry.href"
      >
        <span class="w-[62px] flex-none text-[10.5px] tracking-[0.05em] uppercase text-ink-2">
          {{ $t(`palette.kind.${entry.kind}`) }}
        </span>
        <span class="text-[14px] font-medium tracking-[-0.014em]">{{ entry.title }}</span>
        <span class="ml-auto text-[11.5px] whitespace-nowrap text-ink-2">{{ when(entry.at) }}</span>
      </a>
    </div>
  </dialog>
</template>
