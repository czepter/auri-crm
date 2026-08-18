<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

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

  open.value = (open.value + by + props.assets.length) % props.assets.length
  listsOpen.value = false
}

function onKey(event: KeyboardEvent) {
  if (open.value === null) return

  if (event.key === 'Escape') open.value = null
  if (event.key === 'ArrowRight') step(1)
  if (event.key === 'ArrowLeft') step(-1)
}

// Fetched once the first picture opens rather than on page load: a visitor
// who only scrolls the grid never asks for either.
watch(open, (value, previous) => {
  if (value !== null && previous === null) load()
})

onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))

const WIDTHS = [800, 1200, 1600, 2400]

function srcSet(one: Asset) {
  return WIDTHS.map((w) => `${one.src}?w=${w}&fmt=jpeg ${w}w`).join(', ')
}

function time(iso: string) {
  return new Date(iso).toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}
</script>

<template>
  <div v-if="asset" class="lb">
    <header class="lbtop">
      <button type="button" class="txtbtn" @click="open = null">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6" /></svg>
        {{ $t('gallery.backToGrid') }}
      </button>
      <span class="lbpos">{{ $t('gallery.position', { at: (open ?? 0) + 1, of: assets.length }) }}</span>
      <button type="button" class="txtbtn" @click="open = null">
        {{ $t('common.close') }}
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>
    </header>

    <div class="lbmain">
      <div class="stage">
        <button type="button" class="arrow prev" :title="$t('gallery.previous')" @click="step(-1)">
          <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6" /></svg>
        </button>
        <img :src="`${asset.src}?w=1600&fmt=jpeg`" :srcset="srcSet(asset)" sizes="72vw" alt="" />
        <button type="button" class="arrow next" :title="$t('gallery.next')" @click="step(1)">
          <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
        </button>
      </div>

      <!-- At phone width this becomes a drawer under the picture: the
           actions stay in thumb reach without covering the photograph. -->
      <aside class="rail">
        <div class="acts">
          <!-- A client sees their lists behind one button; a guest sees the
               heart. Same place, same gesture, one menu instead of a toggle. -->
          <div v-if="lists?.length" class="actwrap">
            <button type="button" class="act" :class="{ on: onLists.length }" @click="listsOpen = !listsOpen">
              <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z" /></svg>
              {{ onLists.length ? $t('gallery.onLists', onLists.length) : $t('gallery.addToList') }}
            </button>

            <div v-if="listsOpen" class="listmenu">
              <label v-for="list in lists" :key="list.id">
                <input
                  type="checkbox"
                  :checked="list.keys.includes(asset.key)"
                  @change="toggleList(list)"
                />
                {{ list.name ?? $t('gallery.heart') }}
                <span class="n">{{ list.keys.length }}</span>
              </label>
            </div>
          </div>

          <button v-else type="button" class="act" :class="{ on: isFavorite }" @click="toggleFavorite">
            <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z" /></svg>
            {{ isFavorite ? $t('gallery.unfavorite') : $t('gallery.favorite') }}
          </button>

          <a class="act" :href="`${base}/download`">
            <svg viewBox="0 0 24 24"><path d="M12 4v12M6 11l6 6 6-6M5 20h14" /></svg>
            {{ $t('gallery.download') }}
          </a>
        </div>

        <div class="cmts">
          <div v-for="(comment, index) in forAsset" :key="index" class="cmt" :class="{ anon: !comment.own }">
            <div class="who"><b>{{ comment.author }}</b><span>{{ time(comment.createdAt) }}</span></div>
            <p>{{ comment.body }}</p>
          </div>
          <p v-if="!forAsset.length" class="hint">{{ $t('gallery.noComments') }}</p>
        </div>

        <form class="write" @submit.prevent="sendComment">
          <input v-model="draft" type="text" :placeholder="$t('gallery.commentPlaceholder')" />
        </form>
      </aside>
    </div>

    <nav class="strip">
      <button
        v-for="(thumb, index) in assets"
        :key="thumb.key"
        type="button"
        :class="{ on: index === open }"
        @click="open = index"
      >
        <img :src="`${thumb.src}?w=400&fmt=jpeg`" alt="" loading="lazy" />
      </button>
    </nav>
  </div>
</template>
