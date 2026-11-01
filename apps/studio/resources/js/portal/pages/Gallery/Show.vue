<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { router, useForm, usePage } from '@inertiajs/vue3'
import GalleryLightbox from '@/portal/components/GalleryLightbox.vue'
import { ALL, countOf, selections, visible } from '@/portal/lib/gallerySelections'
import { GRID_WIDTHS, srcSet } from '@/portal/lib/images'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
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
  offers: { downloads: boolean; lists: boolean; comments: boolean }
  assets: Asset[] | null
  // Everything below is null for a guest. They keep the heart, which is the
  // same thing as an unnamed list; they have no link of their own to share
  // and no gates to rotate.
  lists: List[] | null
  // A guest's own hearts, so the filter bar can count them before the
  // lightbox ever opens. Null for a client, whose hearts are a list.
  favorited: string[] | null
  gates: { viewPassword: boolean; downloadPin: boolean; emailVerification: boolean } | null
  guests: { views: number; marks: number; comments: number } | null
}>()

const base = computed(() => `/galerien/${props.gallery.id}`)
const actions = computed(() => `${base.value}/eure-auswahl`)

// One open menu at a time, keyed by asset.
const menu = ref<string | null>(null)

// The lightbox, by index into `shown`. Null is closed.
const openAt = ref<number | null>(null)

// G-02 (#230): which selection the grid is showing. Filtered here rather
// than on the read route — the version's asset list is already in these
// props, so a round trip would buy nothing but a wait.
const { t } = useI18n()
const active = ref(ALL)

const bar = computed(() =>
  selections(
    props.lists?.map((l) => ({ listId: l.listId, name: l.name, keys: l.keys })) ?? null,
    props.favorited,
    { all: t('gallery.all'), heart: t('gallery.heart') },
  ),
)

const shown = computed(() =>
  visible(props.assets ?? [], bar.value.find((s) => s.id === active.value)?.keys ?? null),
)

function openList(list: List, key: string) {
  active.value = list.listId
  openAt.value = visible(props.assets ?? [], list.keys).findIndex((a) => a.key === key)
}

// ---- the couple's own half ----

const openGate = ref<string | null>(null)
const copied = ref(false)
const gateForm = useForm({ secret: '' })
const listForm = useForm({ name: '' })

// A freshly set secret, echoed back once in the response to the request that
// set it, so the couple can copy what they just chose without a second read.
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

// The grid deliberately stays under the proxy's allow-list.
const SIZES = '(max-width: 760px) 50vw, (max-width: 1080px) 33vw, 25vw'

const ROW_HEIGHT = 260

function tileStyle(asset: { width: number | null; height: number | null }) {
  const ratio = asset.width && asset.height ? asset.width / asset.height : 1.5

  return { aspectRatio: String(ratio), flex: `${ratio * 100} 1 ${ratio * ROW_HEIGHT}px` }
}

// The first published asset carries the cover. Nothing in the schema names
// one, so "the first" is the studio's own ordering rather than a guess.
const cover = computed(() => props.assets?.[0] ?? null)

function expiry(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

/* gallery.css, inlined where the same shape recurs on this screen. The sheet
   scopes every rule under [data-theme="gallery"], so nothing here can be
   shared with the portal's own .field / .hint / .err of the same name. */
const INPUT = [
  '[&_input]:w-full [&_input]:rounded-md [&_input]:border [&_input]:border-line-hover',
  '[&_input]:bg-muted [&_input]:px-3.5 [&_input]:py-[13px]',
  '[&_input]:text-[15px] [&_input]:leading-[1.2] [&_input]:font-normal [&_input]:text-foreground',
  '[&_input::placeholder]:text-faint',
].join(' ')
/* On a photograph a box is a foreign object, so the gate's own field is a line. */
const COVER_INPUT = [
  '[&_input]:rounded-none [&_input]:border-0 [&_input]:border-b',
  '[&_input]:border-[rgba(242,237,233,.5)] [&_input]:bg-transparent [&_input]:px-0.5 [&_input]:py-2.5',
  '[&_input]:text-center [&_input]:text-[20px] [&_input]:tracking-[0.22em] [&_input]:text-white',
  '[&_input:focus]:border-action [&_input:focus]:outline-none',
].join(' ')
</script>

<template>
  <!-- data-theme lives on an ancestor, never on the page wrapper itself —
       gallery.css's rules are all `[data-theme="gallery"] .foo` descendant
       selectors, matching the mockup's own <html data-theme="gallery">. -->
  <!-- data-theme stays: tokens.css declares the gallery palette under it,
       and this element is the theme root that has to paint its own
       background — the portal's <html> is themed "portal" (gallery.css). -->
  <div data-theme="gallery" class="block min-h-screen bg-background text-foreground">
    <header
      class="relative grid min-h-[100svh] place-items-center overflow-hidden max-[760px]:min-h-[82svh] before:absolute before:inset-0 before:z-1 before:content-['']"
      :class="
        cover
          ? 'before:bg-[radial-gradient(115%_85%_at_50%_46%,rgba(12,10,9,.58)_0%,rgba(12,10,9,.80)_62%,rgba(12,10,9,.93)_100%)]'
          : 'before:bg-none'
      "
    >
      <img
        v-if="cover"
        :src="`${cover.src}?w=2400&fmt=jpeg`"
        alt=""
        class="absolute inset-0 size-full object-cover object-[50%_38%]"
      />

      <!-- data-tokens: the gate's title is a bare h1, and tokens.css sets one
           unlayered — 24px in the app's own sans, which beats any utility. -->
      <div data-tokens class="relative z-3 w-[min(560px,100%)] px-8 py-11 text-center">
        <span class="text-[13px] leading-none font-bold tracking-[0.18em] whitespace-nowrap uppercase text-foreground no-underline">
          {{ studioName }}
        </span>
        <h1
          class="mt-5 font-[family-name:var(--font-display)] text-[clamp(34px,6vw,56px)] leading-[1.2] font-semibold tracking-[var(--display-tracking)] text-white"
        >
          {{ gallery.title }}
        </h1>
        <p v-if="subtitle" class="mt-5 text-[14px] leading-[1.6] font-normal text-foreground opacity-80">
          {{ subtitle }}
        </p>

        <template v-if="!unlocked">
          <div class="mx-auto mt-11 mb-8 h-px w-12 bg-[rgba(242,237,233,.45)]" />

          <form @submit.prevent="submit">
            <Label
              class="mt-8 block text-left"
              :class="['mx-auto w-[min(340px,100%)] text-center', COVER_INPUT]"
            >
              <span class="mb-2 block text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('gallery.passwordLabel') }}</span>
              <Input v-model="form.password" type="password" autocomplete="off" required />
            </Label>

            <Button class="mt-5 ml-0 w-full justify-center px-3.5 py-[13px] text-[14px]" type="submit" :disabled="form.processing">
              {{ $t('gallery.passwordOpen') }}
            </Button>
          </form>

          <p class="text-[12.5px] leading-[1.55] font-normal text-muted-foreground mt-[9px]">{{ $t('gallery.passwordWhere') }}</p>

          <div
            v-if="form.errors.password"
            class="mt-5 rounded-md border border-[#40251A] bg-late-bg px-3.5 py-3 text-left text-[13px] leading-[1.55] font-normal text-[#E7B190]"
          >
            <b>{{ form.errors.password }}</b>
          </div>
        </template>
      </div>

      <a
        v-if="assets?.length"
        class="absolute bottom-8 left-1/2 z-2 flex -translate-x-1/2 flex-col items-center gap-2.5 text-[10.5px] leading-none font-semibold tracking-[0.2em] uppercase text-muted-foreground no-underline [&_svg]:size-4 [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:stroke-[1.4]"
        href="#raster"
      >
        {{ $t('gallery.scrollCue') }}
        <svg viewBox="0 0 24 24"><path d="M12 5v14M6 13l6 6 6-6" /></svg>
      </a>
    </header>

    <template v-if="unlocked">
      <div
        class="sticky top-0 z-10 mx-auto flex max-w-[1240px] items-center justify-between gap-8 border-b border-border bg-[rgba(14,12,11,.72)] px-8 py-5 backdrop-blur-[14px] backdrop-saturate-[1.4] max-[760px]:flex-wrap"
      >
        <!-- tokens.css .who decides everything but the size and the colour -->
        <span
          class="m-0 pr-[18px] text-[13.5px] leading-[1.35] font-semibold tracking-[-0.015em] text-muted-foreground max-[760px]:hidden [&_b]:font-semibold [&_b]:text-foreground"
        >
          <b>{{ gallery.title }}</b>
          <template v-if="gallery.expiresAt"> · {{ $t('gallery.onlineUntil', { date: expiry(gallery.expiresAt) }) }}</template>
        </span>
        <span
          v-if="bar.length > 1"
          class="mx-auto flex items-center gap-1.5 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          <Button
            v-for="selection in bar"
            :key="selection.id"
            :data-active="active === selection.id"
            type="button"
            variant="portal-filter"
            @click="active = selection.id"
          >
            {{ selection.label }}
            <span class="tabular-nums opacity-55">{{ countOf(selection, assets?.length ?? 0) }}</span>
          </Button>
        </span>

        <Button v-if="offers.downloads" as="a" :href="`/galerien/${gallery.id}/download`">{{ $t('gallery.downloadLink') }}</Button>
      </div>

      <!-- Justified rows: basis and grow follow the aspect ratio, so every row
           fills the width at one height. `columns` left ragged holes at the
           column ends. The trailing spacer keeps the last row from stretching. -->
      <main
        v-if="shown.length"
        id="raster"
        class="mx-auto flex max-w-[1240px] flex-wrap gap-3.5 px-8 pt-11 pb-16"
      >
        <span
          v-for="(asset, index) in shown"
          :key="asset.key"
          class="relative block cursor-zoom-in overflow-hidden rounded-[10px] bg-muted [&_img]:block [&_img]:size-full [&_img]:object-cover [&_img]:transition-transform [&_img]:duration-[1.2s] [&_img]:ease-[cubic-bezier(.22,.61,.36,1)] hover:[&_img]:scale-[1.03]"
          :style="tileStyle(asset)"
          @click="openAt = index"
        >
          <!-- The list menu replaces the single heart as soon as a client is
               signed in; a guest never sees it. -->
          <button
            v-if="offers.lists && lists?.length"
            class="absolute top-2.5 right-2.5 z-4 grid size-8 scale-90 cursor-pointer place-items-center rounded-full border-0 bg-[rgba(12,10,9,.5)] text-white opacity-0 backdrop-blur-[8px] transition-[opacity,transform] duration-[.22s] ease-out group-hover:scale-100 group-hover:opacity-100 aria-expanded:scale-100 aria-expanded:opacity-100 [&_svg]:size-4 [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:stroke-[1.5]"
            type="button"
            :aria-expanded="menu === asset.key"
            @click.stop="menu = menu === asset.key ? null : asset.key"
          >
            <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z" /></svg>
          </button>

          <span v-if="menu === asset.key" class="absolute top-12 right-2.5 z-5 w-[216px] rounded-md border border-border bg-muted p-1.5 shadow-[0_20px_48px_-16px_rgba(0,0,0,.6)]" @click.stop>
            <label v-for="list in lists" :key="list.id" class="flex cursor-pointer items-center gap-2.5 rounded-[var(--r-sm)] px-2.5 py-[9px] text-[13px] leading-none font-medium text-foreground hover:bg-[rgba(255,255,255,.06)]">
              <Checkbox
                :model-value="onList(list, asset.key)"
                @update:model-value="toggle(list, asset.key)"
              />
              {{ list.name ?? $t('gallery.heart') }}
              <span class="ml-auto text-[11.5px] tabular-nums text-muted-foreground">{{ list.keys.length }}</span>
            </label>
          </span>

          <picture>
            <source type="image/avif" :srcset="srcSet(asset.src, 'avif', GRID_WIDTHS)" :sizes="SIZES" />
            <source type="image/webp" :srcset="srcSet(asset.src, 'webp', GRID_WIDTHS)" :sizes="SIZES" />
            <img
              :src="`${asset.src}?w=800&fmt=jpeg`"
              :srcset="srcSet(asset.src, 'jpeg', GRID_WIDTHS)"
              :sizes="SIZES"
              :width="asset.width ?? undefined"
              :height="asset.height ?? undefined"
              alt=""
              loading="lazy"
            />
          </picture>
        </span>
        <span class="grow-[999]" />
      </main>

      <!-- Unlocked over a gallery nobody has published yet: a real state,
           and the studio's link is already out there, so it says so. -->
      <p v-else class="text-[12.5px] leading-[1.55] font-normal text-muted-foreground mt-[9px] px-8 py-16">
        {{ assets?.length ? $t('gallery.listEmpty') : $t('gallery.notPublished') }}
      </p>

      <GalleryLightbox
        v-model="openAt"
        :gallery-id="gallery.id"
        :title="gallery.title"
        :assets="shown"
        :lists="lists"
        :offers="offers"
      />

      <!-- The couple's own half of their gallery, under the pictures. A
           guest never receives any of it. -->
      <section
        v-if="offers.lists && lists"
        class="mx-auto grid max-w-[1240px] gap-16 border-t border-border px-8 py-16 min-[900px]:grid-cols-[minmax(0,1fr)_320px] [&>div:not(:first-child)]:mt-11"
      >
        <div>
          <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('gallery.listsTitle') }}</p>

          <div v-for="(list, index) in lists" :key="list.listId" :class="index > 0 && 'mt-8'">
            <div
              class="mb-3.5 flex flex-wrap items-baseline gap-5 [&_b]:text-[14px] [&_b]:leading-[1.3] [&_b]:font-semibold [&_b]:text-foreground [&_button]:cursor-pointer [&_button]:border-0 [&_button]:bg-none [&_button]:p-0 [&_button]:text-[12.5px] [&_button]:leading-none [&_button]:font-normal [&_button]:text-muted-foreground [&_button:hover]:text-foreground"
            >
              <b>{{ list.name ?? $t('gallery.heart') }}</b>
              <span class="text-[12.5px] leading-[1.55] font-normal text-muted-foreground flex-1">
                {{ $t('gallery.ofCount', { picked: list.keys.length, total: assets?.length ?? 0 }) }}
              </span>
              <template v-if="list.name !== null">
                <button type="button" @click="rename(list)">{{ $t('gallery.renameList') }}</button>
                <button type="button" class="hover:text-destructive!" @click="remove(list)">
                  {{ $t('common.remove') }}
                </button>
              </template>
            </div>

            <div
              v-if="list.covers.length"
              class="grid grid-cols-[repeat(auto-fill,minmax(84px,1fr))] gap-1.5 [&_button]:aspect-square [&_button]:cursor-zoom-in [&_button]:overflow-hidden [&_button]:rounded-[var(--r-sm)] [&_button]:border-0 [&_button]:bg-none [&_button]:p-0 [&_img]:block [&_img]:size-full [&_img]:object-cover"
            >
              <button
                v-for="(src, index) in list.covers"
                :key="src"
                type="button"
                @click="openList(list, list.keys[index])"
              >
                <img :src="`${src}?w=400&fmt=jpeg`" alt="" loading="lazy" />
              </button>
            </div>
            <p v-else class="text-[12.5px] leading-[1.55] font-normal text-muted-foreground mt-[9px]">{{ $t('gallery.listEmpty') }}</p>
          </div>

          <form class="mt-8 flex items-center gap-3.5" @submit.prevent="addList">
            <Input v-model="listForm.name" class="flex-1" type="text" :placeholder="$t('gallery.newListName')" />
            <Button type="submit" variant="ghost" :disabled="!listForm.name">{{ $t('gallery.newList') }}</Button>
          </form>
        </div>

        <div>
          <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('gallery.shareTitle') }}</p>
          <div
            class="flex items-center gap-3.5 rounded-md border border-border p-3.5 [&_button]:cursor-pointer [&_button]:border-0 [&_button]:bg-none [&_button]:p-0 [&_button]:text-[12.5px] [&_button]:leading-none [&_button]:font-medium [&_button]:text-action [&_code]:min-w-0 [&_code]:flex-1 [&_code]:overflow-hidden [&_code]:text-[12.5px] [&_code]:text-ellipsis [&_code]:whitespace-nowrap [&_code]:text-muted-foreground"
          >
            <code>{{ gallery.url }}</code>
            <button type="button" @click="copyLink">
              {{ copied ? $t('gallery.copied') : $t('gallery.copy') }}
            </button>
          </div>

          <!-- What can be done about a link that travelled too far is to
               rotate it, which also turns out every guest already inside.
               The value itself is on the studio's screen (C-17, #253); one
               the couple set themselves is one they already have. -->
          <div
            v-for="gate in GATES"
            :key="gate.key"
            class="flex items-center gap-3.5 border-b border-border py-5 [&_b]:text-[13.5px] [&_b]:leading-[1.3] [&_b]:font-semibold [&_b]:text-foreground [&_button]:cursor-pointer [&_button]:border-0 [&_button]:bg-none [&_button]:p-0 [&_button]:text-[12.5px] [&_button]:leading-none [&_button]:font-normal [&_button]:whitespace-nowrap [&_button]:text-muted-foreground [&_button:hover]:text-foreground"
          >
            <div class="flex-1">
              <b>{{ $t(gate.label) }}</b>
              <div class="text-[12.5px] leading-[1.55] font-normal text-muted-foreground mt-0.5">
                {{ gates?.[gate.on] ? $t('gallery.gateOn') : $t('gallery.gateOff') }}
              </div>
            </div>
            <button type="button" @click="openGate = openGate === gate.key ? null : gate.key">
              {{ gates?.[gate.on] ? $t('gallery.gateRotate') : $t('gallery.gateSet') }}
            </button>
            <button
              v-if="gates?.[gate.on]"
              type="button"
              class="hover:text-destructive!"
              @click="clearGate(gate.key)"
            >
              {{ $t('gallery.gateClear') }}
            </button>
          </div>

          <form v-if="openGate" @submit.prevent="rotate(openGate)">
            <Label class="mt-8 block text-left" :class="INPUT">
              <span class="mb-2 block text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('gallery.gateNewValue') }}</span>
              <Input v-model="gateForm.secret" type="text" autocomplete="off" />
            </Label>
            <p
              v-if="gateForm.errors.secret"
              class="mt-5 rounded-md border border-[#40251A] bg-late-bg px-3.5 py-3 text-left text-[13px] leading-[1.55] font-normal text-[#E7B190]"
            >
              {{ gateForm.errors.secret }}
            </p>
            <Button class="ml-0 w-full justify-center" type="submit" :disabled="gateForm.processing">
              {{ $t('common.save') }}
            </Button>
          </form>

          <p v-if="freshSecret" class="text-[12.5px] leading-[1.55] font-normal text-muted-foreground mt-[9px]"><b>{{ freshSecret }}</b> — {{ $t('gallery.secretOnce') }}</p>
        </div>

        <div v-if="guests">
          <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('gallery.guestsTitle') }}</p>
          <dl
            class="m-0 grid grid-cols-[1fr_auto] gap-x-5 gap-y-3.5 text-[13.5px] leading-none font-normal [&_dd]:m-0 [&_dd]:tabular-nums [&_dd]:text-foreground [&_dt]:text-muted-foreground"
          >
            <dt>{{ $t('gallery.views') }}</dt><dd>{{ guests.views }}</dd>
            <dt>{{ $t('gallery.marks') }}</dt><dd>{{ guests.marks }}</dd>
            <dt>{{ $t('gallery.comments') }}</dt><dd>{{ guests.comments }}</dd>
          </dl>
        </div>
      </section>

      <footer
        class="mx-auto flex max-w-[1240px] flex-wrap justify-between gap-8 border-t border-border px-8 pt-11 pb-16 text-[12.5px] text-muted-foreground"
      >
        <span>{{ studioName }}</span>
        <a href="/impressum">{{ $t('portal.footerImpressum') }}</a>
      </footer>
    </template>
  </div>
</template>
