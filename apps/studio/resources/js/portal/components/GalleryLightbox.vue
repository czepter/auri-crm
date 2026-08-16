<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

type Asset = { key: string; width: number | null; height: number | null; src: string }
type Comment = { key: string; body: string; createdAt: string }

const props = defineProps<{
  galleryId: string
  title: string | null
  assets: Asset[]
  // Null for a signed-in client: their marks are named lists owned by the
  // Contact (P-06, #225), and the heart here would be a second, weaker
  // answer to the same question.
  guestFavourites: boolean
}>()

const open = defineModel<number | null>({ required: true })

const favorited = ref(new Set<string>())
const comments = ref<Comment[]>([])
const draft = ref('')

const asset = computed(() => (open.value === null ? null : props.assets[open.value] ?? null))
const forAsset = computed(() => comments.value.filter((c) => c.key === asset.value?.key))
const isFavorite = computed(() => (asset.value ? favorited.value.has(asset.value.key) : false))

const base = computed(() => `/galerien/${props.galleryId}`)

async function load() {
  const response = await fetch(`${base.value}/beteiligung`, { headers: { Accept: 'application/json' } })

  if (!response.ok) return

  const body = await response.json()
  favorited.value = new Set<string>(body.favorited)
  comments.value = body.comments
}

// One CSRF-bearing POST helper; Inertia's own router is for page visits, and
// none of these three change the page.
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
}

function onKey(event: KeyboardEvent) {
  if (open.value === null) return

  if (event.key === 'Escape') open.value = null
  if (event.key === 'ArrowRight') step(1)
  if (event.key === 'ArrowLeft') step(-1)
}

// A guest's own marks and everyone's comments, fetched once the first
// picture opens rather than on page load: a visitor who only scrolls the
// grid never asks for either.
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
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <div v-if="asset" class="lb" @click.self="open = null">
    <header class="lbtop">
      <button type="button" class="txtbtn" @click="open = null">{{ $t('gallery.backToGrid') }}</button>
      <span class="lbpos">{{ title }} · {{ (open ?? 0) + 1 }} / {{ assets.length }}</span>
      <button type="button" class="txtbtn" @click="open = null">{{ $t('common.close') }}</button>
    </header>

    <div class="lbsplit">
      <div class="stage">
        <button type="button" class="arrow" :title="$t('gallery.previous')" @click="step(-1)">‹</button>
        <img :src="`${asset.src}?w=1600&fmt=jpeg`" :srcset="srcSet(asset)" sizes="72vw" alt="" />
        <button type="button" class="arrow" :title="$t('gallery.next')" @click="step(1)">›</button>
      </div>

      <aside class="lbrail">
        <!-- A guest keeps the heart. A signed-in client marks pictures onto
             named lists instead, from the tile menu on the grid. -->
        <button v-if="guestFavourites" type="button" class="act" :class="{ on: isFavorite }" @click="toggleFavorite">
          <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z" /></svg>
          {{ isFavorite ? $t('gallery.unfavorite') : $t('gallery.favorite') }}
        </button>

        <div class="cmts">
          <div v-for="(comment, index) in forAsset" :key="index" class="cmt">
            <div class="txt">{{ comment.body }}</div>
            <div class="when">{{ time(comment.createdAt) }}</div>
          </div>
          <p v-if="!forAsset.length" class="hint">{{ $t('gallery.noComments') }}</p>
        </div>

        <form class="lbwrite" @submit.prevent="sendComment">
          <input v-model="draft" type="text" :placeholder="$t('gallery.commentPlaceholder')" />
        </form>
      </aside>
    </div>

    <div class="strip">
      <button
        v-for="(thumb, index) in assets"
        :key="thumb.key"
        type="button"
        :class="{ on: index === open }"
        @click="open = index"
      >
        <img :src="`${thumb.src}?w=400&fmt=jpeg`" alt="" loading="lazy" />
      </button>
    </div>
  </div>
</template>
