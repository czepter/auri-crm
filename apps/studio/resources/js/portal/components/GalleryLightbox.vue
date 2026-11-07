<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { srcSet, STAGE_WIDTHS } from '@/portal/lib/images'
import { keyIntent, stepIndex } from '@/portal/lib/lightbox'
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
  offers: { downloads: boolean; lists: boolean; comments: boolean }
}>()

const open = defineModel<number | null>({ required: true })

const favorited = ref(new Set<string>())
const comments = ref<Comment[]>([])
const draft = ref('')
const listsOpen = ref(false)

const asset = computed(() => (open.value === null ? null : props.assets[open.value] ?? null))
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

async function toggleFavorite() {
  if (!asset.value) return

  const key = asset.value.key
  const response = await post('/favorit', { key })

  if (!response.ok) return

  const { favorited: on } = await response.json()
  const next = new Set(favorited.value)
  on ? next.add(key) : next.delete(key)
  favorited.value = next
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
  if (!asset.value || draft.value.trim() === '') return

  const response = await post('/kommentar', { key: asset.value.key, body: draft.value })

  if (!response.ok) return

  const { comment } = await response.json()
  comments.value = [...comments.value, comment]
  draft.value = ''
}

function step(by: number) {
  if (open.value === null) return

  open.value = stepIndex(open.value, by, props.assets.length)
  listsOpen.value = false
}

function onKey(event: KeyboardEvent) {
  if (open.value === null) return

  // Not every keydown target is an element — one dispatched on the window
  // has no matches() — and an arrow key inside a field belongs to the field.
  const target = event.target as Element | null
  const typing = typeof target?.matches === 'function' && target.matches('input, textarea, select')
  const intent = keyIntent(event.key, typing)

  if (intent === 'close') open.value = null
  if (intent === 'prev') step(-1)
  if (intent === 'next') step(1)
}

watch(open, (value, previous) => {
  // sendComment reads the asset key at send time, so a draft that outlives
  // the picture it was typed under would file against the next one.
  draft.value = ''

  // Fetched once the first picture opens rather than on page load: a visitor
  // who only scrolls the grid never asks for either.
  if (value !== null && previous === null) load()
})

onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))

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
  <div v-if="asset" data-tokens class="fixed inset-0 z-50 flex flex-col bg-background">
    <header class="flex items-center justify-between gap-5 px-8 py-5">
      <button
        type="button"
        class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-[13px] leading-none font-medium text-muted-foreground no-underline hover:text-foreground [&_svg]:size-3.5 [&_svg]:stroke-[1.6] [&_svg]:flex-none [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]"
        @click="open = null"
      >
        <svg viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6" /></svg>
        {{ $t('gallery.backToGrid') }}
      </button>
      <span class="text-[12.5px] leading-none font-normal tabular-nums text-muted-foreground">{{ $t('gallery.position', { at: (open ?? 0) + 1, of: assets.length }) }}</span>
      <button
        type="button"
        class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-[13px] leading-none font-medium text-muted-foreground no-underline hover:text-foreground [&_svg]:size-3.5 [&_svg]:stroke-[1.6] [&_svg]:flex-none [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]"
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
        class="relative mt-5 grid min-h-0 grid-cols-[minmax(0,1fr)] grid-rows-[minmax(0,1fr)] place-items-center [&>img]:block [&>img]:max-h-full [&>img]:max-w-full [&>img]:object-contain"
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
        <img :src="`${asset.src}?w=1600&fmt=jpeg`" :srcset="srcSet(asset.src, 'jpeg', STAGE_WIDTHS)" sizes="72vw" alt="" />
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
          <div v-if="offers.lists && lists?.length" class="relative">
            <button
              type="button"
              class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-[13px] leading-none font-medium no-underline hover:text-foreground [&_svg]:size-4 [&_svg]:stroke-[1.5] [&_svg]:flex-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]"
              :class="onLists.length ? 'text-action [&_svg]:fill-current' : 'text-muted-foreground [&_svg]:fill-none'"
              @click="listsOpen = !listsOpen"
            >
              <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z" /></svg>
              {{ onLists.length ? $t('gallery.onLists', onLists.length) : $t('gallery.addToList') }}
            </button>

            <div v-if="listsOpen" class="absolute top-7 left-0 z-5 w-[216px] rounded-md border border-border bg-muted p-1.5 shadow-[0_20px_48px_-16px_rgba(0,0,0,.6)]">
              <label v-for="list in lists" :key="list.id" class="flex cursor-pointer items-center gap-2.5 rounded-[var(--r-sm)] px-2.5 py-[9px] text-[13px] leading-none font-medium text-foreground hover:bg-[rgba(255,255,255,.06)]">
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
            class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-[13px] leading-none font-medium no-underline hover:text-foreground [&_svg]:size-4 [&_svg]:stroke-[1.5] [&_svg]:flex-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]"
            :class="isFavorite ? 'text-action [&_svg]:fill-current' : 'text-muted-foreground [&_svg]:fill-none'"
            @click="toggleFavorite"
          >
            <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z" /></svg>
            {{ isFavorite ? $t('gallery.unfavorite') : $t('gallery.favorite') }}
          </button>

          <a v-if="offers.downloads" class="inline-flex cursor-pointer items-center gap-2 border-0 bg-none p-0 text-[13px] leading-none font-medium text-muted-foreground no-underline hover:text-foreground [&_svg]:size-4 [&_svg]:stroke-[1.5] [&_svg]:flex-none [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]" :href="`${base}/download`">
            <svg viewBox="0 0 24 24"><path d="M12 4v12M6 11l6 6 6-6M5 20h14" /></svg>
            {{ $t('gallery.download') }}
          </a>
        </div>

        <div v-if="offers.comments" class="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto">
          <div v-for="(comment, index) in forAsset" :key="index">
            <!-- tokens.css .who decides the face here too — gallery.css only lays the row out. -->
            <div
              class="mb-1 flex items-baseline gap-2 pr-[18px] text-[13.5px] leading-[1.35] font-semibold tracking-[-0.015em] [&_b]:text-[12.5px] [&_b]:leading-none [&_span]:text-[11.5px] [&_span]:leading-none [&_span]:font-normal [&_span]:tabular-nums [&_span]:text-faint"
              :class="comment.own ? '[&_b]:font-semibold [&_b]:text-foreground' : '[&_b]:font-medium [&_b]:text-muted-foreground!'"
            >
              <b>{{ comment.author }}</b><span>{{ time(comment.createdAt) }}</span>
            </div>
            <p class="m-0 text-[13.5px] leading-[1.55] font-normal text-foreground">{{ comment.body }}</p>
          </div>
          <p v-if="!forAsset.length" class="mt-[9px] text-[12.5px] leading-[1.55] font-normal text-muted-foreground">
            {{ $t('gallery.noComments') }}
          </p>
        </div>

        <form v-if="offers.comments" @submit.prevent="sendComment">
          <Input
            v-model="draft"
            type="text"
            :placeholder="$t('gallery.commentPlaceholder')"
          />
        </form>
      </aside>
    </div>

    <nav
      class="flex gap-1.5 overflow-x-auto px-8 py-5 [&_button]:size-16 [&_button]:flex-none [&_button]:cursor-pointer [&_button]:overflow-hidden [&_button]:rounded-[var(--r-sm)] [&_button]:border-0 [&_button]:bg-none [&_button]:p-0 [&_button]:opacity-45 [&_img]:block [&_img]:size-full [&_img]:object-cover"
    >
      <button
        v-for="(thumb, index) in assets"
        :key="thumb.key"
        type="button"
        :class="index === open && 'opacity-100!'"
        @click="open = index"
      >
        <img :src="`${thumb.src}?w=400&fmt=jpeg`" alt="" loading="lazy" />
      </button>
    </nav>
  </div>
</template>
