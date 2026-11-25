<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { srcSet, STAGE_WIDTHS } from '@/portal/lib/images'
import { favoriteSync, keyIntent, stepIndex, swipeIntent } from '@/portal/lib/lightbox'
import { shareLink } from '@/portal/lib/share'
import { useI18n } from 'vue-i18n'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'

type Asset = { key: string; width: number | null; height: number | null; src: string }
type Comment = { key: string; body: string; author: string; own: boolean; createdAt: string }
type List = { id: string; name: string | null; keys: string[] }

const props = defineProps<{
  galleryId: string
  title: string | null
  assets: Asset[]
  // G-01 (#229): two roles, one screen. A guest gets the heart; a signed-in
  // client gets their lists — null is the guest.
  lists: List[] | null
  offers: { downloads: boolean; lists: boolean; comments: boolean; hide: boolean; share: boolean }
  shareUrl: string
  // Ausgeblendete Bilder zeigt nur der eigene Eintrag der Leiste; dort heißt die Aktion Einblenden.
  unhiding: boolean
}>()

const open = defineModel<number | null>({ required: true })
const { t } = useI18n()
const shareNote = ref<string | null>(null)

const favorited = ref(new Set<string>())
const comments = ref<Comment[]>([])
const draft = ref('')
const listsOpen = ref(false)
const sending = ref(false)
const bursting = ref(false)
const stageLoaded = ref(false)
const lastIndex = ref(0)
const root = ref<HTMLElement | null>(null)
const listsRoot = ref<HTMLElement | null>(null)
const strip = ref<HTMLElement | null>(null)

// Keeps the last picture on screen while the overlay fades out.
const at = computed(() => open.value ?? lastIndex.value)
const asset = computed(() => props.assets[at.value] ?? null)
const forAsset = computed(() => comments.value.filter((c) => c.key === asset.value?.key))
const isFavorite = computed(() => (asset.value ? favorited.value.has(asset.value.key) : false))
const base = computed(() => `/galerien/${props.galleryId}`)

// The mockup's own label: the button says how many lists this picture is on,
// not "Favorit" — for a client the heart was never one list.
const onLists = computed(() =>
  asset.value === null ? [] : (props.lists ?? []).filter((l) => l.keys.includes(asset.value!.key)),
)

async function load() {
  const response = await fetch(`${base.value}/beteiligung`, { headers: { Accept: 'application/json' } })

  if (!response.ok) return

  const body = await response.json()
  favorited.value = new Set<string>(body.favorited)
  comments.value = body.comments
}

// One CSRF-bearing POST helper; Inertia's router is for page visits, and
// none of these change the page.
async function post(path: string, payload: Record<string, string>) {
  return fetch(base.value + path, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
    },
    body: JSON.stringify(payload),
  })
}

function setFavorite(key: string, on: boolean) {
  const next = new Set(favorited.value)
  on ? next.add(key) : next.delete(key)
  favorited.value = next
}

const syncFavorite = favoriteSync(async (key) => {
  try {
    const response = await post('/favorit', { key })

    return response.ok ? (await response.json()).favorited : null
  } catch {
    return null
  }
})

async function toggleFavorite() {
  if (!asset.value) return

  const key = asset.value.key
  const server = favorited.value.has(key)
  const want = !server

  setFavorite(key, want)

  if (want) {
    bursting.value = true
    setTimeout(() => (bursting.value = false), 180)
  }

  const settled = await syncFavorite(key, want, server)

  if (settled !== null) setFavorite(key, settled)
}

// The same checkbox set the grid tile opens, so a picture lands on a list
// from wherever the client happens to be looking at it.
function toggleList(list: List) {
  if (!asset.value) return

  router.post(
    `${base.value}/eure-auswahl/listen/${list.id}/bild`,
    { key: asset.value.key },
    { preserveScroll: true, preserveState: false },
  )
}

async function sendComment() {
  if (!asset.value || sending.value || draft.value.trim() === '') return

  sending.value = true

  try {
    const response = await post('/kommentar', { key: asset.value.key, body: draft.value })

    if (!response.ok) return

    const { comment } = await response.json()
    comments.value = [...comments.value, comment]
    draft.value = ''
  } finally {
    sending.value = false
  }
}

async function hide() {
  if (!asset.value) return

  const response = await post('/ausblenden', { key: asset.value.key })

  if (response.ok) router.reload({ only: ['assets', 'hiddenAssets'] })
}

async function share() {
  const result = await shareLink(props.shareUrl, props.title)

  shareNote.value = result === 'copied' ? t('gallery.copied') : result === 'failed' ? t('gallery.copyFailed') : null
  setTimeout(() => (shareNote.value = null), 2000)
}

// Nach dem Ausblenden rückt die Liste zusammen; die Ansicht bleibt auf einem Bild.
watch(
  () => props.assets.length,
  (length) => {
    if (open.value === null) return

    if (length === 0) open.value = null
    else if (open.value >= length) open.value = length - 1
  },
)

function step(by: number) {
  if (open.value === null) return

  open.value = stepIndex(open.value, by, props.assets.length)
  listsOpen.value = false
}

function trapFocus(event: KeyboardEvent) {
  const items = [...(root.value?.querySelectorAll<HTMLElement>('a[href], button, input') ?? [])]
  const first = items[0]
  const last = items[items.length - 1]

  if (!first || !root.value) return

  const active = document.activeElement

  if (!root.value.contains(active) || (event.shiftKey && active === first)) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

let swipeFrom: { x: number; y: number } | null = null

function swipeStart(event: PointerEvent) {
  swipeFrom = event.pointerType === 'touch' ? { x: event.clientX, y: event.clientY } : null
}

function swipeEnd(event: PointerEvent) {
  if (!swipeFrom) return

  const intent = swipeIntent(event.clientX - swipeFrom.x, event.clientY - swipeFrom.y)
  swipeFrom = null

  if (intent) step(intent === 'next' ? 1 : -1)
}

function onKey(event: KeyboardEvent) {
  if (open.value === null) return

  if (event.key === 'Tab') return trapFocus(event)

  // Not every keydown target is an element — one dispatched on the window
  // has no matches() — and an arrow key inside a field belongs to the field.
  const target = event.target as Element | null
  const typing = typeof target?.matches === 'function' && target.matches('input, textarea, select')
  const intent = keyIntent(event.key, typing)

  if (intent === 'close') {
    if (listsOpen.value) listsOpen.value = false
    else open.value = null
  }
  if (intent === 'prev') step(-1)
  if (intent === 'next') step(1)
}

function preload(index: number) {
  const neighbour = props.assets[index]

  if (!neighbour) return

  const image = new Image()
  image.sizes = '72vw'
  image.srcset = srcSet(neighbour.src, 'jpeg', STAGE_WIDTHS)
  image.src = `${neighbour.src}?w=1600&fmt=jpeg`
}

function onOutside(event: PointerEvent) {
  if (!listsRoot.value?.contains(event.target as Node)) listsOpen.value = false
}

watch(listsOpen, (value) => {
  if (value) window.addEventListener('pointerdown', onOutside)
  else window.removeEventListener('pointerdown', onOutside)
})

let returnFocus: HTMLElement | null = null
let bodyOverflow = ''

async function shown(index: number) {
  stageLoaded.value = false
  preload(stepIndex(index, 1, props.assets.length))
  preload(stepIndex(index, -1, props.assets.length))

  await nextTick()

  strip.value?.children[index]?.scrollIntoView({
    inline: 'center',
    block: 'nearest',
    behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
  })
}

watch(open, async (value, previous) => {
  if (value !== null) {
    lastIndex.value = value
    shown(value)
  }

  if (value !== null && previous === null) {
    returnFocus = document.activeElement as HTMLElement | null
    bodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    await nextTick()
    root.value?.focus()
  }

  if (value === null && previous !== null) {
    document.body.style.overflow = bodyOverflow
    listsOpen.value = false
    returnFocus?.focus()
  }

  // sendComment reads the asset key at send time, so a draft that outlives
  // the picture it was typed under would file against the next one.
  draft.value = ''

  // Fetched once the first picture opens rather than on page load: a visitor
  // who only scrolls the grid never asks for either.
  if (value !== null && previous === null) load()
})

onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => {
  window.removeEventListener('keydown', onKey)
  window.removeEventListener('pointerdown', onOutside)

  if (open.value !== null) document.body.style.overflow = bodyOverflow
})

function time(iso: string) {
  return new Date(iso).toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}
</script>

<template>
  <!-- data-tokens: the svg reset in tokens.css is unlayered and would beat
       every stroke-width here. Switched off, the reset's own defaults are
       spelled out per class below. -->
  <Transition
    enter-active-class="transition-opacity duration-200 motion-reduce:transition-none"
    leave-active-class="transition-opacity duration-150 motion-reduce:transition-none"
    enter-from-class="opacity-0"
    leave-to-class="opacity-0"
  >
  <div
    v-if="open !== null && asset"
    ref="root"
    data-tokens
    role="dialog"
    aria-modal="true"
    tabindex="-1"
    class="fixed inset-0 z-50 flex flex-col bg-background outline-none"
  >
    <header class="flex items-center justify-between gap-5 px-8 py-5">
      <button
        type="button"
        class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-small leading-none font-medium text-muted-foreground no-underline hover:text-foreground [&_svg]:size-3.5 [&_svg]:stroke-[1.6] [&_svg]:flex-none [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]"
        @click="open = null"
      >
        <svg viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6" /></svg>
        {{ $t('gallery.backToGrid') }}
      </button>
      <span class="text-[12.5px] leading-none font-normal tabular-nums text-muted-foreground">{{ $t('gallery.position', { at: at + 1, of: assets.length }) }}</span>
      <button
        type="button"
        class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-small leading-none font-medium text-muted-foreground no-underline hover:text-foreground [&_svg]:size-3.5 [&_svg]:stroke-[1.6] [&_svg]:flex-none [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]"
        @click="open = null"
      >
        {{ $t('common.close') }}
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>
    </header>

    <div
      class="grid min-h-0 flex-1 grid-cols-[minmax(0,1fr)_300px] grid-rows-[minmax(0,1fr)] gap-8 px-8 max-[860px]:grid-cols-[minmax(0,1fr)] max-[860px]:grid-rows-[minmax(0,1fr)_auto]"
    >
      <!-- mt-5 is tokens.css .stage's own margin, which this stage never asked
           for and has always had: two files spell the same name. -->
      <div
        class="relative mt-5 grid min-h-0 touch-pan-y grid-cols-[minmax(0,1fr)] grid-rows-[minmax(0,1fr)] place-items-center [&>img]:col-start-1 [&>img]:row-start-1 [&>img]:block [&>img]:size-full [&>img]:object-contain"
        @pointerdown="swipeStart"
        @pointerup="swipeEnd"
        @pointercancel="swipeFrom = null"
      >
        <button
          type="button"
          class="absolute top-1/2 grid size-10 -translate-y-1/2 cursor-pointer place-items-center rounded-full border-0 bg-[rgba(255,255,255,.08)] text-foreground hover:bg-[rgba(255,255,255,.16)] max-[860px]:bg-[rgba(255,255,255,.14)] [&_svg]:size-5 [&_svg]:stroke-[1.6] [&_svg]:flex-none [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round] left-0"
          :title="$t('gallery.previous')"
          :aria-label="$t('gallery.previous')"
          @click="step(-1)"
        >
          <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6" /></svg>
        </button>
        <img :src="`${asset.src}?w=400&fmt=jpeg`" alt="" class="blur-sm" aria-hidden="true" />
        <img
          :key="asset.key"
          :src="`${asset.src}?w=1600&fmt=jpeg`"
          :srcset="srcSet(asset.src, 'jpeg', STAGE_WIDTHS)"
          sizes="72vw"
          alt=""
          class="relative transition-opacity duration-300 motion-reduce:transition-none"
          :class="stageLoaded ? 'opacity-100' : 'opacity-0'"
          @load="stageLoaded = true"
        />
        <button
          type="button"
          class="absolute top-1/2 grid size-10 -translate-y-1/2 cursor-pointer place-items-center rounded-full border-0 bg-[rgba(255,255,255,.08)] text-foreground hover:bg-[rgba(255,255,255,.16)] max-[860px]:bg-[rgba(255,255,255,.14)] [&_svg]:size-5 [&_svg]:stroke-[1.6] [&_svg]:flex-none [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round] right-0"
          :title="$t('gallery.next')"
          :aria-label="$t('gallery.next')"
          @click="step(1)"
        >
          <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
        </button>
      </div>

      <!-- At phone width this becomes a drawer under the picture: the
           actions stay in thumb reach without covering the photograph. -->
      <aside
        class="flex min-h-0 flex-col gap-5 py-3.5 max-[860px]:max-h-[38svh] max-[860px]:border-t max-[860px]:border-border max-[860px]:pt-5"
      >
        <div class="flex flex-wrap items-start gap-8">
          <!-- A client sees their lists behind one button; a guest sees the
               heart. Same place, same gesture, one menu instead of a toggle. -->
          <div v-if="offers.lists && lists?.length" ref="listsRoot" class="relative">
            <button
              type="button"
              class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-small leading-none font-medium no-underline hover:text-foreground [&_svg]:size-4 [&_svg]:stroke-[1.5] [&_svg]:flex-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]"
              :class="onLists.length ? 'text-action [&_svg]:fill-current' : 'text-muted-foreground [&_svg]:fill-none'"
              @click="listsOpen = !listsOpen"
            >
              <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z" /></svg>
              {{ onLists.length ? $t('gallery.onLists', onLists.length) : $t('gallery.addToList') }}
            </button>

            <div v-if="listsOpen" class="absolute top-7 left-0 z-5 w-[216px] rounded-md border border-line-hover bg-muted p-1.5">
              <label v-for="list in lists" :key="list.id" class="flex cursor-pointer items-center gap-2.5 rounded-[var(--r-sm)] px-2.5 py-[9px] text-small leading-none font-medium text-foreground hover:bg-[rgba(255,255,255,.06)]">
                <Checkbox
                  :model-value="list.keys.includes(asset.key)"
                  @update:model-value="toggleList(list)"
                />
                {{ list.name ?? $t('gallery.heart') }}
                <span class="ml-auto text-[11.5px] tabular-nums text-muted-foreground">{{ list.keys.length }}</span>
              </label>
            </div>
          </div>

          <button
            v-else-if="offers.lists"
            type="button"
            class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-small leading-none font-medium no-underline hover:text-foreground [&_svg]:size-4 [&_svg]:stroke-[1.5] [&_svg]:flex-none [&_svg]:transition-transform [&_svg]:duration-200 motion-reduce:[&_svg]:transition-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]"
            :class="[isFavorite ? 'text-action [&_svg]:fill-current' : 'text-muted-foreground [&_svg]:fill-none', bursting && '[&_svg]:scale-125']"
            @click="toggleFavorite"
          >
            <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z" /></svg>
            {{ isFavorite ? $t('gallery.unfavorite') : $t('gallery.favorite') }}
          </button>

          <a v-if="offers.downloads" class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-small leading-none font-medium text-muted-foreground no-underline hover:text-foreground [&_svg]:size-4 [&_svg]:stroke-[1.5] [&_svg]:flex-none [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]" :href="`${base}/download`">
            <svg viewBox="0 0 24 24"><path d="M12 4v12M6 11l6 6 6-6M5 20h14" /></svg>
            {{ $t('gallery.download') }}
          </a>

          <button
            v-if="offers.hide"
            type="button"
            class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-small leading-none font-medium text-muted-foreground no-underline hover:text-foreground [&_svg]:size-4 [&_svg]:stroke-[1.5] [&_svg]:flex-none [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]"
            @click="hide"
          >
            <svg viewBox="0 0 24 24"><path d="M4 4l16 16M10 6.2A9 9 0 0121 12a9 9 0 01-3 3.5M6.5 7.5A9 9 0 003 12c1.5 3.5 5 6 9 6 1.1 0 2.1-.2 3-.5" /></svg>
            {{ unhiding ? $t('gallery.unhide') : $t('gallery.hide') }}
          </button>

          <button
            v-if="offers.share"
            type="button"
            class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-small leading-none font-medium text-muted-foreground no-underline hover:text-foreground [&_svg]:size-4 [&_svg]:stroke-[1.5] [&_svg]:flex-none [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]"
            @click="share"
          >
            <svg viewBox="0 0 24 24"><path d="M12 15V4M7 9l5-5 5 5M5 13v7h14v-7" /></svg>
            {{ shareNote ?? $t('gallery.share') }}
          </button>
        </div>

        <TransitionGroup
          v-if="offers.comments"
          tag="div"
          enter-active-class="transition-opacity duration-300 motion-reduce:transition-none"
          enter-from-class="opacity-0"
          class="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto"
        >
          <div v-for="(comment, index) in forAsset" :key="`${comment.createdAt}-${index}`">
            <!-- tokens.css .who decides the face here too — gallery.css only lays the row out. -->
            <div
              class="mb-1 flex items-baseline gap-2 pr-[18px] text-[13.5px] leading-[1.35] font-semibold tracking-[-0.015em] [&_b]:text-[12.5px] [&_b]:leading-none [&_span]:text-[11.5px] [&_span]:leading-none [&_span]:font-normal [&_span]:tabular-nums [&_span]:text-faint"
              :class="comment.own ? '[&_b]:font-semibold [&_b]:text-foreground' : '[&_b]:font-medium [&_b]:text-muted-foreground!'"
            >
              <b>{{ comment.author }}</b><span>{{ time(comment.createdAt) }}</span>
            </div>
            <p class="m-0 text-[13.5px] leading-[1.55] font-normal text-foreground">{{ comment.body }}</p>
          </div>
          <p v-if="!forAsset.length" key="empty" class="mt-[9px] text-[12.5px] leading-[1.55] font-normal text-muted-foreground">
            {{ $t('gallery.noComments') }}
          </p>
        </TransitionGroup>

        <form v-if="offers.comments" @submit.prevent="sendComment">
          <Input
            v-model="draft"
            :readonly="sending"
            :aria-busy="sending"
            class="transition-opacity"
            :class="sending && 'opacity-60'"
            type="text"
            :placeholder="$t('gallery.commentPlaceholder')"
          />
        </form>
      </aside>
    </div>

    <nav
      ref="strip"
      class="flex gap-1.5 overflow-x-auto px-8 py-5 [&_button]:size-16 [&_button]:flex-none [&_button]:cursor-pointer [&_button]:overflow-hidden [&_button]:rounded-[var(--r-sm)] [&_button]:border-0 [&_button]:bg-none [&_button]:p-0 [&_button]:opacity-45 [&_img]:block [&_img]:size-full [&_img]:object-cover"
    >
      <button
        v-for="(thumb, index) in assets"
        :key="thumb.key"
        type="button"
        :class="index === at && 'opacity-100!'"
        @click="open = index"
      >
        <img :src="`${thumb.src}?w=400&fmt=jpeg`" alt="" loading="lazy" />
      </button>
    </nav>
  </div>
  </Transition>
</template>
