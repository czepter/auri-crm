<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import GalleryLightbox from '@/portal/components/GalleryLightbox.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

type Branding = { displayName: string | null }
type Asset = { key: string; width: number | null; height: number | null; src: string }
type List = {
  id: string
  listId: string
  name: string | null
  keys: string[]
  covers: string[]
  changedAt: string
}

const props = defineProps<{
  gallery: {
    id: string
    title: string | null
    date: string | null
    venue: string | null
    expiresAt: string | null
    url: string
  }
  unlocked: boolean
  assets: Asset[] | null
  // Everything below is null for a guest. They keep the heart, which is the
  // same thing as an unnamed list; they have no link of their own to share
  // and no gates to rotate.
  lists: List[] | null
  gates: { viewPassword: boolean; downloadPin: boolean; emailVerification: boolean } | null
  guests: { views: number; marks: number; comments: number } | null
}>()

const base = computed(() => `/galerien/${props.gallery.id}`)
const actions = computed(() => `${base.value}/eure-auswahl`)

// One open menu at a time, keyed by asset.
const menu = ref<string | null>(null)

// The lightbox, by index. Null is closed.
const openAt = ref<number | null>(null)

// ---- the couple's own half ----

const openGate = ref<string | null>(null)
const copied = ref(false)
const gateForm = useForm({ secret: '' })
const listForm = useForm({ name: '' })

// A freshly set secret is readable exactly once, in the response to the
// request that set it — the column is hashed the moment it is saved, so this
// flash is the whole window.
const freshSecret = computed(() => usePage().props.flash?.secret as string | undefined)

const GATES = [
  { key: 'ansicht', label: 'gallery.gateView', on: 'viewPassword' },
  { key: 'download', label: 'gallery.gateDownload', on: 'downloadPin' },
] as const

function rotate(gate: string) {
  gateForm.post(`${actions.value}/tore/${gate}`, {
    preserveScroll: true,
    onSuccess: () => {
      gateForm.reset()
      openGate.value = null
    },
  })
}

function clearGate(gate: string) {
  router.post(`${actions.value}/tore/${gate}`, { secret: null }, { preserveScroll: true })
}

function addList() {
  listForm.post(`${actions.value}/listen`, { preserveScroll: true, onSuccess: () => listForm.reset() })
}

function rename(list: List) {
  const name = window.prompt('', list.name ?? '')

  if (name) {
    router.patch(`${actions.value}/listen/${list.listId}`, { name }, { preserveScroll: true })
  }
}

function remove(list: List) {
  router.delete(`${actions.value}/listen/${list.listId}`, { preserveScroll: true })
}

// A browser may refuse the clipboard outright (permission, insecure origin).
// The link is on screen either way; an unhandled rejection would be the only
// thing that broke.
function copyLink() {
  navigator.clipboard
    ?.writeText(props.gallery.url)
    .then(() => (copied.value = true))
    .catch(() => (copied.value = false))
}

function onList(list: List, key: string) {
  return list.keys.includes(key)
}

function toggle(list: List, key: string) {
  router.post(
    `${actions.value}/listen/${list.id}/bild`,
    { key },
    { preserveScroll: true, preserveState: false },
  )
}

// T-08's shared branding, the same wordmark PortalFooter reads — a gallery
// guest sees the studio's name, never the internal project it belongs to.
const studioName = computed(() => (usePage().props.portalBranding as Branding | undefined)?.displayName)

const form = useForm({ password: '' })

function submit() {
  form.post(`/galerien/${props.gallery.id}/passwort`)
}

const subtitle = computed(() => {
  const parts = [props.gallery.date, props.gallery.venue].filter(Boolean)

  if (props.assets?.length) {
    parts.push(`${props.assets.length} Bilder`)
  }

  return parts.join(' · ')
})

// G-08's own allowed widths (App\Gallery\Support\ImageVariant::WIDTHS). The
// grid is at most four columns of a 1440px page, so the two largest exist
// for retina rather than for layout.
const WIDTHS = [400, 800, 1200, 1600]
const SIZES = '(max-width: 760px) 50vw, (max-width: 1080px) 33vw, 25vw'

function srcSet(asset: Asset, format: string) {
  return WIDTHS.map((w) => `${asset.src}?w=${w}&fmt=${format} ${w}w`).join(', ')
}

// The first published asset carries the cover. Nothing in the schema names
// one, so "the first" is the studio's own ordering rather than a guess.
const cover = computed(() => props.assets?.[0] ?? null)

function expiry(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <!-- data-theme lives on an ancestor, never on the page wrapper itself —
       gallery.css's rules are all `[data-theme="gallery"] .foo` descendant
       selectors, matching the mockup's own <html data-theme="gallery">. -->
  <div data-theme="gallery">
    <header class="cover" :class="{ bare: !cover }">
      <img v-if="cover" :src="`${cover.src}?w=2400&fmt=jpeg`" alt="" />

      <div class="lock">
        <span class="gmark">{{ studioName }}</span>
        <h1 class="gtitle" style="margin-top: var(--s4)">{{ gallery.title }}</h1>
        <p v-if="subtitle" class="gsub">{{ subtitle }}</p>

        <template v-if="!unlocked">
          <div class="hair" />

          <form @submit.prevent="submit">
            <Label class="field">
              <span>{{ $t('gallery.passwordLabel') }}</span>
              <Input v-model="form.password" type="password" autocomplete="off" required />
            </Label>

            <Button class="wide" type="submit" :disabled="form.processing">
              {{ $t('gallery.passwordOpen') }}
            </Button>
          </form>

          <p class="hint">{{ $t('gallery.passwordWhere') }}</p>

          <div v-if="form.errors.password" class="err">
            <b>{{ form.errors.password }}</b>
          </div>
        </template>
      </div>

      <a v-if="assets?.length" class="scrollcue" href="#raster">
        {{ $t('gallery.scrollCue') }}
        <svg viewBox="0 0 24 24"><path d="M12 5v14M6 13l6 6 6-6" /></svg>
      </a>
    </header>

    <template v-if="unlocked">
      <div class="gbar">
        <span class="who">
          <b>{{ gallery.title }}</b>
          <template v-if="gallery.expiresAt"> · {{ $t('gallery.onlineUntil', { date: expiry(gallery.expiresAt) }) }}</template>
        </span>
        <Button as="a" :href="`/galerien/${gallery.id}/download`">{{ $t('gallery.downloadLink') }}</Button>
      </div>

      <main v-if="assets?.length" id="raster" class="gwall">
        <span v-for="(asset, index) in assets" :key="asset.key" class="tile" @click="openAt = index">
          <!-- The list menu replaces the single heart as soon as a client is
               signed in; a guest never sees it. -->
          <button
            v-if="lists?.length"
            class="listbtn"
            type="button"
            :aria-expanded="menu === asset.key"
            @click.stop="menu = menu === asset.key ? null : asset.key"
          >
            <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z" /></svg>
          </button>

          <span v-if="menu === asset.key" class="lists" @click.stop>
            <label v-for="list in lists" :key="list.id">
              <input type="checkbox" :checked="onList(list, asset.key)" @change="toggle(list, asset.key)" />
              {{ list.name ?? $t('gallery.heart') }}
              <span class="n">{{ list.keys.length }}</span>
            </label>
          </span>

          <picture>
            <source type="image/avif" :srcset="srcSet(asset, 'avif')" :sizes="SIZES" />
            <source type="image/webp" :srcset="srcSet(asset, 'webp')" :sizes="SIZES" />
            <img
              :src="`${asset.src}?w=800&fmt=jpeg`"
              :srcset="srcSet(asset, 'jpeg')"
              :sizes="SIZES"
              :width="asset.width ?? undefined"
              :height="asset.height ?? undefined"
              alt=""
              loading="lazy"
            />
          </picture>
        </span>
      </main>

      <!-- Unlocked over a gallery nobody has published yet: a real state,
           and the studio's link is already out there, so it says so. -->
      <p v-else class="hint" style="padding: var(--s7) var(--s5)">{{ $t('gallery.notPublished') }}</p>

      <GalleryLightbox
        v-model="openAt"
        :gallery-id="gallery.id"
        :title="gallery.title"
        :assets="assets ?? []"
        :lists="lists"
      />

      <!-- The couple's own half of their gallery, under the pictures. A
           guest never receives any of it. -->
      <section v-if="lists" class="mine">
        <div class="block">
          <p class="lab">{{ $t('gallery.listsTitle') }}</p>

          <div v-for="list in lists" :key="list.listId" class="listblock">
            <div class="listbar">
              <b>{{ list.name ?? $t('gallery.heart') }}</b>
              <span class="hint">
                {{ $t('gallery.ofCount', { picked: list.keys.length, total: assets?.length ?? 0 }) }}
              </span>
              <template v-if="list.name !== null">
                <button type="button" @click="rename(list)">{{ $t('gallery.renameList') }}</button>
                <button type="button" class="warn" @click="remove(list)">{{ $t('common.remove') }}</button>
              </template>
            </div>

            <div v-if="list.covers.length" class="picks">
              <button
                v-for="(src, index) in list.covers"
                :key="src"
                type="button"
                @click="openAt = assets?.findIndex((a) => a.key === list.keys[index]) ?? null"
              >
                <img :src="`${src}?w=400&fmt=jpeg`" alt="" loading="lazy" />
              </button>
            </div>
            <p v-else class="hint">{{ $t('gallery.listEmpty') }}</p>
          </div>

          <form class="newlist" @submit.prevent="addList">
            <input v-model="listForm.name" type="text" :placeholder="$t('gallery.newListName')" />
            <Button type="submit" variant="ghost" :disabled="!listForm.name">{{ $t('gallery.newList') }}</Button>
          </form>
        </div>

        <div class="block">
          <p class="lab">{{ $t('gallery.shareTitle') }}</p>
          <div class="link">
            <code>{{ gallery.url }}</code>
            <button type="button" @click="copyLink">
              {{ copied ? $t('gallery.copied') : $t('gallery.copy') }}
            </button>
          </div>

          <!-- No value is ever shown: the secrets are hashed the moment they
               are saved and gone for everyone, the studio included. What can
               be done about a link that travelled too far is to rotate it,
               which also turns out every guest already inside. -->
          <div v-for="gate in GATES" :key="gate.key" class="gate">
            <div style="flex: 1">
              <b>{{ $t(gate.label) }}</b>
              <div class="hint">{{ gates?.[gate.on] ? $t('gallery.gateOn') : $t('gallery.gateOff') }}</div>
            </div>
            <button type="button" @click="openGate = openGate === gate.key ? null : gate.key">
              {{ gates?.[gate.on] ? $t('gallery.gateRotate') : $t('gallery.gateSet') }}
            </button>
            <button v-if="gates?.[gate.on]" type="button" class="warn" @click="clearGate(gate.key)">
              {{ $t('gallery.gateClear') }}
            </button>
          </div>

          <form v-if="openGate" @submit.prevent="rotate(openGate)">
            <Label class="field">
              <span>{{ $t('gallery.gateNewValue') }}</span>
              <Input v-model="gateForm.secret" type="text" autocomplete="off" />
            </Label>
            <p v-if="gateForm.errors.secret" class="err">{{ gateForm.errors.secret }}</p>
            <Button class="mid" type="submit" :disabled="gateForm.processing">{{ $t('common.save') }}</Button>
          </form>

          <p v-if="freshSecret" class="hint"><b>{{ freshSecret }}</b> — {{ $t('gallery.secretOnce') }}</p>
        </div>

        <div v-if="guests" class="block">
          <p class="lab">{{ $t('gallery.guestsTitle') }}</p>
          <dl class="stats">
            <dt>{{ $t('gallery.views') }}</dt><dd>{{ guests.views }}</dd>
            <dt>{{ $t('gallery.marks') }}</dt><dd>{{ guests.marks }}</dd>
            <dt>{{ $t('gallery.comments') }}</dt><dd>{{ guests.comments }}</dd>
          </dl>
        </div>
      </section>

      <footer class="gfoot">
        <span>{{ studioName }}</span>
        <a href="/impressum">{{ $t('portal.footerImpressum') }}</a>
      </footer>
    </template>
  </div>
</template>
