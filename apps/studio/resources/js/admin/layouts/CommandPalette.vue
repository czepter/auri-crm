<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { helpPanel } from '@/lib/helpPanel'
import { holeIndex, treffer, type HelpEintrag } from '@/lib/helpSearch'
import { rank } from '@/admin/lib/paletteRank'

/**
 * C-03 (#237): the search is a tool in the sidebar, not a field in a header.
 *
 * A native <dialog> opened with showModal(). The focus trap, Esc and the
 * inert background all come from the browser; the only key this listens for
 * is ⌘K, because that one has no native equivalent.
 *
 * It opens on what one has just been looking at rather than on an empty mask,
 * mixed in visit order — grouped by kind it would be sorted by something
 * nobody is searching for. Below that, once there is a query, the help index
 * joins in as a second group.
 */
type Recent = { kind: string; title: string; href: string; at: string }

const dialog = ref<HTMLDialogElement | null>(null)
const recent = () => (usePage().props.recent as Recent[] | null) ?? []

const frage = ref('')
const index = ref<HelpEintrag[]>([])

const results = ref<Omit<Recent, 'at'>[]>([])
let timer: ReturnType<typeof setTimeout> | undefined

watch(frage, (value) => {
  steered = false
  clearTimeout(timer)

  if (value.trim().length < 2) {
    results.value = []
    return
  }

  timer = setTimeout(async () => {
    const response = await fetch(`/suche?q=${encodeURIComponent(value)}`, {
      headers: { Accept: 'application/json' },
    })
    results.value = (await response.json()).results
  }, 250)
})

const rows = computed(() => rank<Omit<Recent, 'at'> & { at?: string }>(recent(), results.value, frage.value))

// Follows the best match until the arrow keys or the pointer pick a row, then
// tracks that row, not its position: a reorder under the cursor must not move
// the target before Enter.
let steered = false
const highlighted = ref<string | null>(null)
watch(rows, (list) => {
  if (!steered || !list.some((entry) => entry.href === highlighted.value)) highlighted.value = list[0]?.href ?? null
})

function step(event: KeyboardEvent) {
  const list = rows.value
  const at = list.findIndex((entry) => entry.href === highlighted.value)

  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault()
    steered = true
    const next = (at + (event.key === 'ArrowDown' ? 1 : -1) + list.length) % list.length
    highlighted.value = list[next]?.href ?? null
    document.getElementById(`palette-${highlighted.value}`)?.scrollIntoView({ block: 'nearest' })
  }
  else if (event.key === 'Enter' && at >= 0) {
    event.preventDefault()
    dialog.value?.close()
    router.visit(list[at].href)
  }
}

async function open() {
  steered = false
  dialog.value?.showModal()
  highlighted.value = rows.value[0]?.href ?? null
  index.value = await holeIndex()
}

function waehlen(slug: string) {
  dialog.value?.close()

  if (((usePage().props.help as unknown[] | null) ?? []).length > 0) {
    helpPanel.oeffnen(slug)
    return
  }

  router.visit(`/hilfe/${slug}`)
}

// AdminLayout mountet mit jeder Inertia-Navigation neu (kein persistentes
// Layout, siehe helpPanel.ts) — hier zu vergessen heißt: der in der Palette
// gewählte Artikel klebt sonst über jedem folgenden Screen.
function onKey(event: KeyboardEvent) {
  if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
    event.preventDefault()
    open()
  }
}

onMounted(() => {
  helpPanel.navigiert()
  addEventListener('keydown', onKey)
})
onUnmounted(() => removeEventListener('keydown', onKey))

defineExpose({ open })

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
    class="mx-auto mt-[12vh] mb-auto w-[min(560px,calc(100vw-48px))] rounded-md border-0 bg-paper p-0 text-foreground backdrop:bg-[rgba(29,29,31,0.28)] backdrop:backdrop-blur-[3px] transition-[opacity,transform] duration-200 ease-[cubic-bezier(0.23,1,0.32,1)] starting:scale-[0.96] starting:opacity-0 motion-reduce:transition-none"
    @click.self="dialog?.close()"
  >
    <!-- upper third: the list grows downward, the eye stays -->
    <Input :aria-label="$t('palette.placeholder')"
      v-model="frage"
      class="rounded-none border-0 bg-transparent px-5.5 py-4.5 text-title leading-[1.3] tracking-[-0.022em] shadow-[inset_0_-1px_0_var(--rule)] placeholder:text-faint"
      type="search"
      :placeholder="$t('palette.placeholder')"
      autofocus
      @keydown="step"
    />

    <div class="max-h-[52vh] overflow-auto pt-1.5 pb-2">
      <div v-if="!frage && rows.length" class="px-5.5 pt-3 pb-1 text-small text-ink-2">
        {{ $t('palette.recent') }}
      </div>
      <TransitionGroup tag="div" role="listbox" move-class="transition-transform duration-150 ease-[cubic-bezier(0.23,1,0.32,1)] motion-reduce:transition-none">
        <a
          v-for="entry in rows"
          :id="`palette-${entry.href}`"
          :key="entry.href"
          role="option"
          :aria-selected="entry.href === highlighted"
          class="flex items-baseline gap-3 px-5.5 py-2 text-foreground no-underline hover:bg-sunk focus-visible:bg-sunk focus-visible:outline-0"
          :class="entry.href === highlighted && 'bg-sunk'"
          :href="entry.href"
          @pointermove="steered = true; highlighted = entry.href"
        >
          <span class="w-[62px] flex-none text-small text-ink-2">
            {{ $t(`palette.kind.${entry.kind}`) }}
          </span>
          <span class="text-ui font-medium tracking-[-0.014em]">{{ entry.title }}</span>
          <span v-if="entry.at" class="ml-auto text-small whitespace-nowrap text-ink-2">{{ when(entry.at) }}</span>
        </a>
      </TransitionGroup>

      <template v-if="frage">
        <div class="px-5.5 pt-3 pb-1 text-small text-ink-2">
          {{ $t('help.title') }}
        </div>

        <button
          v-for="eintrag in treffer(index, frage)"
          :key="eintrag.slug"
          class="flex w-full cursor-pointer items-baseline gap-3 border-0 bg-transparent px-5.5 py-2 text-left text-foreground hover:bg-sunk"
          type="button"
          @click="waehlen(eintrag.slug)"
        >
          <span class="w-[62px] flex-none text-small text-ink-2">
            {{ $t(`help.art.${eintrag.art}`) }}
          </span>
          <span class="text-ui font-medium tracking-[-0.014em]">{{ eintrag.titel }}</span>
        </button>
      </template>
    </div>
  </dialog>
</template>
