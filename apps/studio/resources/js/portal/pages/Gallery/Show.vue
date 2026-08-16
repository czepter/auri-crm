<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import GalleryLightbox from '@/portal/components/GalleryLightbox.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

type Branding = { displayName: string | null }
type Asset = { key: string; width: number | null; height: number | null; src: string }
type List = { id: string; name: string | null; keys: string[] }

const props = defineProps<{
  gallery: { id: string; title: string | null; date: string | null; venue: string | null; expiresAt: string | null }
  unlocked: boolean
  assets: Asset[] | null
  // P-06: null for a guest. A guest keeps the heart, which is the same
  // thing as this — a list without a name.
  lists: List[] | null
}>()

// One open menu at a time, keyed by asset.
const menu = ref<string | null>(null)

// The lightbox, by index. Null is closed.
const openAt = ref<number | null>(null)

function onList(list: List, key: string) {
  return list.keys.includes(key)
}

function toggle(list: List, key: string) {
  router.post(
    `/galerien/${props.gallery.id}/eure-auswahl/listen/${list.id}/bild`,
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
        :guest-favourites="!lists?.length"
      />

      <footer class="gfoot">
        <span>{{ studioName }}</span>
        <a href="/impressum">{{ $t('portal.footerImpressum') }}</a>
      </footer>
    </template>
  </div>
</template>
