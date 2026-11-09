<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { router, useForm, usePage } from '@inertiajs/vue3'
import GalleryIntro from '@/portal/components/GalleryIntro.vue'
import GalleryLightbox from '@/portal/components/GalleryLightbox.vue'
import PortalBack from '@/portal/components/PortalBack.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import { dayDate, daysUntil } from '@/portal/lib/dates'
import { introPending, introRows, markIntroSeen } from '@/portal/lib/galleryIntro'
import { ALL, countOf, HIDDEN, selections, visible, withHidden } from '@/portal/lib/gallerySelections'
import { shareLink } from '@/portal/lib/share'
import { longPress, hideOnScroll } from '@/portal/lib/gestures'
import { GRID_WIDTHS, srcSet } from '@/portal/lib/images'
import { designAttrs, objectPosition, type GalleryDesign } from '@/portal/lib/galleryDesign'
import { favoriteSync } from '@/portal/lib/lightbox'
import { tileStyle } from '@/portal/lib/tile'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { CopyButton } from '@/ui/copy-button'
import { HoldToConfirm } from '@/ui/hold-to-confirm'
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
  // Set for the couple only; it is what mounts the portal shell.
  project: { id: string; name: string } | null
  unlocked: boolean
  design: GalleryDesign
  coverImage: { key: string | null; focal: { x: number; y: number } | null }
  offers: { downloads: boolean; lists: boolean; comments: boolean; hide: boolean; share: boolean }
  intro: boolean
  locked: boolean
  downloadAfterPayment: boolean
  // Nur für den Kunden: die Rechnung, die den Download freigibt.
  payUrl: string | null
  setName: string | null
  shareUrl: string
  assets: Asset[] | null
  // Was dieser Kunde für sich ausgeblendet hat; für Gäste immer leer.
  hiddenAssets: Asset[]
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

function closeMenu(event: Event) {
  if (menu.value && !(event.target as Element).closest('[data-list-menu]')) menu.value = null
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') menu.value = null
}

// The click that ends a long press would open the lightbox on top of the menu.
let swallowClick = false
let pressKey: string | null = null

const press = longPress(() => {
  menu.value = pressKey
  swallowClick = true
})

function pressStart(event: PointerEvent, key: string) {
  if (event.pointerType !== 'touch' || !(props.offers.lists && hasNamedLists.value)) return

  pressKey = key
  press.start(event.clientX, event.clientY)
}

function pressMove(event: PointerEvent) {
  press.move(event.clientX, event.clientY)
}

// The menu opens under the finger that is still down; its release must not tick a list.
function menuClick(event: Event) {
  if (!swallowClick) return

  swallowClick = false
  event.preventDefault()
}

function openTile(index: number) {
  if (swallowClick) {
    swallowClick = false

    return
  }

  openAt.value = index
}

// Header: on a phone the filter bar leaves on the way down and returns on the way up.
const barHidden = ref(false)
const phone = typeof matchMedia === 'function' ? matchMedia('(max-width: 760px)') : null
const scrolled = hideOnScroll()
let frame = 0

function onScroll() {
  if (frame) return

  frame = requestAnimationFrame(() => {
    frame = 0
    const hidden = scrolled(window.scrollY)
    barHidden.value = !!phone?.matches && hidden
  })
}

onMounted(() => {
  document.addEventListener('pointerdown', closeMenu)
  window.addEventListener('pageshow', resetDownload)
  document.addEventListener('keydown', onKeydown)
  window.addEventListener('scroll', onScroll, { passive: true })
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', closeMenu)
  document.removeEventListener('keydown', onKeydown)
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('pageshow', resetDownload)
  cancelAnimationFrame(frame)
  press.cancel()
})

// The lightbox, by index into `shown`. Null is closed.
const openAt = ref<number | null>(null)

// G-02 (#230): which selection the grid is showing. Filtered here rather
// than on the read route — the version's asset list is already in these
// props, so a round trip would buy nothing but a wait.
const { t } = useI18n()
const active = ref(ALL)

const bar = computed(() =>
  withHidden(
    selections(
      props.lists?.map((l) => ({ listId: l.listId, name: l.name, keys: l.keys })) ?? null,
      props.favorited,
      { all: t('gallery.all'), heart: t('gallery.heart') },
    ),
    props.offers.hide ? props.hiddenAssets.map((a) => a.key) : [],
    t('gallery.hidden'),
  ),
)

const shown = computed(() =>
  visible(
    active.value === HIDDEN ? props.hiddenAssets : (props.assets ?? []),
    bar.value.find((s) => s.id === active.value)?.keys ?? null,
  ),
)

// Der letzte ausgeblendete Eintrag verschwindet mit seinem Reiter.
watch(bar, (entries) => {
  if (!entries.some((s) => s.id === active.value)) active.value = ALL
})

const introLines = computed(() => introRows(props.offers))
const introOpen = ref(false)

onMounted(() => {
  introOpen.value = props.unlocked && introPending(props.gallery.id, props.intro, introLines.value)
})

function introDone() {
  introOpen.value = false
  markIntroSeen(props.gallery.id)
}

const shareNote = ref<string | null>(null)

async function shareGallery() {
  const result = await shareLink(props.shareUrl, props.gallery.title)

  shareNote.value = result === 'copied' ? t('gallery.copied') : result === 'failed' ? t('gallery.copyFailed') : null
  setTimeout(() => (shareNote.value = null), 2000)
}

const tabs = ref<HTMLElement | null>(null)
const tabEls = new Map<string, HTMLElement>()
const indicator = ref<{ x: number; w: number } | null>(null)
const slide = ref(false)

function tabRef(id: string, el: unknown) {
  const node = el && '$el' in (el as object) ? (el as { $el: HTMLElement }).$el : (el as HTMLElement | null)
  node ? tabEls.set(id, node) : tabEls.delete(id)
}

watch(
  [active, bar, tabs],
  () => {
    const el = tabEls.get(active.value)
    indicator.value = el ? { x: el.offsetLeft, w: el.offsetWidth } : null
    requestAnimationFrame(() => (slide.value = true))
  },
  { flush: 'post', immediate: true },
)

const loaded = ref(new Set<string>())

function markLoaded(key: string) {
  // A function ref runs on every render; re-adding would re-render forever.
  if (loaded.value.has(key)) return

  loaded.value = new Set(loaded.value).add(key)
}

function cachedImg(key: string, el: unknown) {
  if (el instanceof HTMLImageElement && el.complete && el.naturalWidth) markLoaded(key)
}

const downloading = ref(false)

function resetDownload() {
  downloading.value = false
}

function openList(list: List, key: string) {
  active.value = list.listId
  openAt.value = visible(props.assets ?? [], list.keys).findIndex((a) => a.key === key)
}

// ---- the couple's own half ----

const openGate = ref<string | null>(null)
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

function onList(list: List, key: string) {
  return list.keys.includes(key)
}

function toggle(list: Pick<List, 'id'>, key: string, onFinish?: () => void) {
  router.post(
    `${actions.value}/listen/${list.id}/bild`,
    { key },
    { preserveScroll: true, preserveState: false, onFinish },
  )
}

// T-08's shared branding, the same wordmark PortalFooter reads — a gallery
// guest sees the studio's name, never the internal project it belongs to.
const studioName = computed(() => (usePage().props.portalBranding as Branding | undefined)?.displayName)

const expiring = computed(() => (props.gallery.expiresAt ? daysUntil(props.gallery.expiresAt) <= 14 : false))

const form = useForm({ password: '' })

function submit() {
  form.post(`/galerien/${props.gallery.id}/passwort`)
}

const subtitle = computed(() => {
  const parts = [props.setName, props.gallery.date && dayDate(props.gallery.date), props.gallery.venue].filter(Boolean)

  if (props.assets?.length) {
    parts.push(`${props.assets.length} Bilder`)
  }

  return parts.join(' · ')
})

// The grid deliberately stays under the proxy's allow-list.
const SIZES = '(max-width: 760px) 50vw, (max-width: 1080px) 33vw, 25vw'

// The version names its cover; a gallery published before it did falls back to the first asset.
const cover = computed(() => props.assets?.find((asset) => asset.key === props.coverImage.key) ?? props.assets?.[0] ?? null)

// A split cover puts the title beside the picture, not on it.
const overlaid = computed(() => cover.value !== null && (!props.unlocked || props.design.cover !== 'geteilt'))
const rows = computed(() => props.design.flow === 'zeilen')

// A guest's hearts are this session's; a client's are the lists.
const hearts = ref(new Set(props.favorited ?? []))

// A tap shows at once; a client's override is dropped when the visit ends,
// which is also the rollback when it failed.
const pending = ref(new Map<string, boolean>())
const bursting = ref<string | null>(null)

// The menu only earns its place once there is a second list to choose.
const hasNamedLists = computed(() => !!props.lists?.some((l) => l.name !== null))
const hasFavourites = computed(() => !!props.lists?.some((l) => l.keys.length))

function isHeart(key: string) {
  if (pending.value.has(key)) return pending.value.get(key)!

  return props.lists ? props.lists.some((l) => l.keys.includes(key)) : hearts.value.has(key)
}

function setHeart(key: string, on: boolean) {
  const next = new Set(hearts.value)
  on ? next.add(key) : next.delete(key)
  hearts.value = next
}

const syncHeart = favoriteSync(async (key) => {
  try {
    const response = await fetch(`${base.value}/favorit`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
      },
      body: JSON.stringify({ key }),
    })

    return response.ok ? (await response.json()).favorited : null
  } catch {
    return null
  }
})

async function heart(key: string) {
  const server = isHeart(key)
  const want = !server

  if (want) {
    bursting.value = key
    setTimeout(() => (bursting.value = null), 180)
  }

  if (props.lists) {
    pending.value = new Map(pending.value).set(key, want)
    toggle({ id: 'herz' }, key, () => {
      const next = new Map(pending.value)
      next.delete(key)
      pending.value = next
    })

    return
  }

  setHeart(key, want)

  const settled = await syncHeart(key, want, server)

  if (settled !== null) setHeart(key, settled)
}

/* gallery.css, inlined where the same shape recurs on this screen. The sheet
   scopes every rule under [data-theme="gallery"], so nothing here can be
   shared with the portal's own .field / .hint / .err of the same name. */
const INPUT = [
  '[&_input]:w-full [&_input]:rounded-md [&_input]:border [&_input]:border-input',
  '[&_input]:bg-muted [&_input]:px-3.5 [&_input]:py-[13px]',
  '[&_input]:text-[15px] [&_input]:leading-[1.2] [&_input]:font-normal [&_input]:text-foreground',
  '[&_input::placeholder]:text-faint',
].join(' ')
/* On a photograph a box is a foreign object, so the gate's own field is a line. */
const COVER_INPUT = [
  '[&_input]:rounded-none [&_input]:border-0 [&_input]:border-b',
  '[&_input]:border-[rgba(242,237,233,.65)] [&_input]:bg-transparent [&_input]:px-0.5 [&_input]:py-2.5',
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
  <div class="block min-h-screen bg-background text-foreground" :class="project && 'max-[760px]:pb-[calc(56px+env(safe-area-inset-bottom))]'" v-bind="designAttrs(design)">
    <header
      :data-gcover="unlocked ? design.cover : 'zentriert'"
      class="relative grid place-items-center overflow-hidden before:absolute before:inset-0 before:z-1 before:content-['']"
      :class="[
        overlaid ? 'text-white' : 'text-foreground',
        unlocked ? 'min-h-[34svh]' : 'min-h-[100svh] max-[760px]:min-h-[82svh]',
        overlaid
          ? 'before:bg-[linear-gradient(rgba(12,10,9,.55),rgba(12,10,9,.55)),radial-gradient(115%_85%_at_50%_46%,rgba(12,10,9,.58)_0%,rgba(12,10,9,.80)_62%,rgba(12,10,9,.93)_100%)]'
          : 'before:bg-none',
      ]"
    >
      <img
        v-if="cover"
        :src="`${cover.src}?w=2400&fmt=jpeg`"
        alt=""
        class="absolute inset-0 size-full object-cover"
        :style="{ objectPosition: objectPosition(coverImage.focal) }"
      />

      <div v-if="project" data-tokens>
        <PortalHeader over />
      </div>

      <!-- data-tokens: the gate's title is a bare h1, and tokens.css sets one
           unlayered — 24px in the app's own sans, which beats any utility. -->
      <div data-tokens class="relative z-3 w-[min(560px,100%)] px-8 py-11 text-center">
        <span v-if="!project" class="mb-5 inline-block text-[13px] leading-none font-bold tracking-[0.18em] whitespace-nowrap uppercase text-inherit no-underline" :class="overlaid && 'rounded-full bg-[rgba(12,10,9,.72)] px-3.5 py-2'">
          {{ studioName }}
        </span>
        <h1
          class="font-[family-name:var(--font-display)] text-[clamp(34px,6vw,56px)] leading-[1.2] font-semibold tracking-[var(--display-tracking)] text-inherit"
        >
          {{ gallery.title }}
        </h1>
        <p v-if="subtitle" class="mt-5 text-[14px] leading-[1.6] font-normal text-inherit" :class="overlaid ? 'inline-block rounded-full bg-[rgba(12,10,9,.72)] px-3.5 py-1.5' : 'opacity-80'">
          {{ subtitle }}
        </p>
        <br v-if="subtitle && gallery.expiresAt && overlaid" />
        <p v-if="gallery.expiresAt" class="mt-2 text-[14px] leading-[1.6] font-normal" :class="[overlaid || expiring ? 'inline-block rounded-full px-3.5 py-1.5' : '', expiring ? 'bg-due-bg font-semibold text-due' : overlaid ? 'bg-[rgba(12,10,9,.72)] text-inherit' : 'text-inherit opacity-80']">
          {{ $t('gallery.onlineUntil', { date: dayDate(gallery.expiresAt) }) }}
        </p>
        <p v-if="downloadAfterPayment" class="mt-2 text-[14px] leading-[1.6] font-normal text-inherit opacity-80">{{ $t('gallery.downloadAfterPayment') }}</p>

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
        v-if="assets?.length && !unlocked"
        class="absolute bottom-8 left-1/2 z-2 flex -translate-x-1/2 flex-col items-center gap-2.5 rounded-full bg-[rgba(12,10,9,.72)] px-4 py-3 text-[14px] leading-none font-semibold tracking-[0.12em] uppercase text-white [text-shadow:0_1px_12px_rgba(0,0,0,.7)] no-underline [&_svg]:size-4 [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:stroke-[1.4]"
        href="#raster"
      >
        {{ $t('gallery.scrollCue') }}
        <svg viewBox="0 0 24 24"><path d="M12 5v14M6 13l6 6 6-6" /></svg>
      </a>
    </header>

    <template v-if="unlocked">
      <div
        class="sticky top-0 z-10 mx-auto flex max-w-[1240px] items-center justify-between gap-8 border-b border-border bg-[var(--bg)] px-8 py-5 max-[760px]:flex-wrap max-[760px]:gap-4 max-[760px]:px-4 transition-transform duration-300 ease-[cubic-bezier(.23,1,.32,1)] motion-reduce:transition-none"
        :class="barHidden && 'max-[760px]:-translate-y-full'"
      >
        <PortalBack v-if="project" class="min-[761px]:hidden" :href="`/projekte/${project.id}`" />
        <!-- tokens.css .who decides everything but the size and the colour -->
        <span
          class="m-0 pr-[18px] text-[14px] leading-[1.35] font-semibold tracking-[-0.015em] text-muted-foreground max-[760px]:hidden [&_b]:font-semibold [&_b]:text-foreground"
        >
          <b>{{ gallery.title }}</b>
          <template v-if="gallery.expiresAt"> · {{ $t('gallery.onlineUntil', { date: dayDate(gallery.expiresAt) }) }}</template>
        </span>
        <span
          v-if="bar.length > 1"
          ref="tabs"
          class="relative mx-auto flex min-w-0 items-center gap-1.5 overflow-x-auto [scrollbar-width:none] max-[760px]:order-last max-[760px]:mx-0 max-[760px]:w-full [&::-webkit-scrollbar]:hidden"
        >
          <span
            v-if="indicator"
            aria-hidden="true"
            class="pointer-events-none absolute inset-y-0 left-0 rounded-[var(--r-pill)] bg-foreground motion-reduce:transition-none"
            :class="slide && 'transition-[transform,width] duration-300 ease-[cubic-bezier(.23,1,.32,1)]'"
            :style="{ width: `${indicator.w}px`, transform: `translateX(${indicator.x}px)` }"
          />
          <Button
            v-for="selection in bar"
            :key="selection.id"
            :ref="(el: unknown) => tabRef(selection.id, el)"
            :data-active="active === selection.id"
            class="relative min-h-11 data-[active=true]:bg-transparent"
            type="button"
            variant="portal-filter"
            @click="active = selection.id"
          >
            {{ selection.label }}
            <span class="tabular-nums opacity-55">{{ countOf(selection, assets?.length ?? 0) }}</span>
          </Button>
        </span>

        <Button v-if="offers.share" class="max-[760px]:ml-auto max-[760px]:shrink-0" variant="portal-ghost" type="button" @click="shareGallery">
          {{ shareNote ?? $t('gallery.share') }}
        </Button>

        <Button v-if="offers.downloads" class="max-[760px]:ml-auto max-[760px]:shrink-0" as="a" variant="portal" :state="downloading ? 'loading' : undefined" :href="`/galerien/${gallery.id}/download`" @click="downloading = true">
          <svg viewBox="0 0 24 24"><path d="M12 4v12M6 11l6 6 6-6M5 20h14" /></svg>
          {{ $t('gallery.download') }}
        </Button>
        <Button v-else-if="payUrl" class="max-[760px]:ml-auto max-[760px]:shrink-0" as="a" variant="portal" :href="payUrl">
          {{ $t('gallery.payInvoice') }}
        </Button>
      </div>

      <!-- Justified rows: basis and grow follow the aspect ratio, so every row
           fills the width at one height. `columns` left ragged holes at the
           column ends. tileStyle caps how far the last row may stretch. -->
      <TransitionGroup
        v-if="shown.length"
        id="raster"
        data-tokens
        tag="main"
        name="tile"
        class="mx-auto max-w-[1240px] px-8 pt-11 pb-16 max-[760px]:px-4 max-[760px]:pt-4 max-[760px]:pb-8"
        :class="rows ? 'flex flex-wrap justify-center max-[760px]:grid max-[760px]:grid-cols-2' : 'block'"
      >
        <span
          v-for="(asset, index) in shown"
          :key="asset.key"
          :data-loaded="loaded.has(asset.key)"
          class="group relative block cursor-zoom-in select-none overflow-hidden [-webkit-touch-callout:none] rounded-[10px] bg-muted max-[760px]:rounded-md [&_img]:block [&_img]:size-full [&_img]:object-cover"
          :class="rows && 'max-[760px]:[aspect-ratio:1!important] max-[760px]:[flex:none!important] max-[760px]:[max-width:none!important]'"
          :style="tileStyle(asset)"
          @click="openTile(index)"
          @pointerdown="pressStart($event, asset.key)"
          @pointermove="pressMove"
          @pointerup="press.cancel"
          @pointercancel="press.cancel"
          @contextmenu="offers.lists && hasNamedLists && $event.preventDefault()"
        >
          <span aria-hidden="true" class="pointer-events-none absolute inset-0 animate-pulse bg-foreground/5 transition-opacity duration-300 group-data-[loaded=true]:animate-none group-data-[loaded=true]:opacity-0 motion-reduce:animate-none" />

          <!-- The list menu replaces the single heart as soon as a client is
               signed in; a guest never sees it. -->
          <button
            v-if="offers.lists && hasNamedLists"
            class="absolute top-2.5 right-2.5 z-4 grid size-11 cursor-pointer place-items-center rounded-full border-0 bg-[rgba(12,10,9,.55)] text-white backdrop-blur-[8px] [&_svg]:size-5 [&_svg]:stroke-current [&_svg]:stroke-[1.5]"
            :class="isHeart(asset.key) ? '[&_svg]:fill-current' : '[&_svg]:fill-none'"
            data-list-menu
            type="button"
            :aria-label="$t('gallery.favorite')"
            :aria-expanded="menu === asset.key"
            @click.stop="menu = menu === asset.key ? null : asset.key"
          >
            <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z" /></svg>
          </button>

          <button
            v-else-if="offers.lists"
            class="absolute top-2.5 right-2.5 z-4 grid size-11 cursor-pointer place-items-center rounded-full border-0 bg-[rgba(12,10,9,.55)] text-white backdrop-blur-[8px] [&_svg]:size-5 [&_svg]:stroke-current [&_svg]:stroke-[1.5] [&_svg]:transition-transform [&_svg]:duration-150 motion-reduce:[&_svg]:transition-none"
            :class="[isHeart(asset.key) ? '[&_svg]:fill-current' : '[&_svg]:fill-none', bursting === asset.key && '[&_svg]:scale-125']"
            type="button"
            :aria-label="isHeart(asset.key) ? $t('gallery.unfavorite') : $t('gallery.favorite')"
            @click.stop="heart(asset.key)"
          >
            <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.5A4.5 4.5 0 0112 8a4.5 4.5 0 017 2.5c0 5.1-7 9.5-7 9.5z" /></svg>
          </button>

          <span v-if="menu === asset.key" data-list-menu class="absolute top-12 right-2.5 z-5 w-[216px] rounded-md border border-border bg-muted p-1.5 shadow-[0_20px_48px_-16px_rgba(0,0,0,.6)]" @click.stop="menuClick">
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
              class="tile-img opacity-0 group-data-[loaded=true]:opacity-100"
              :ref="(el: unknown) => cachedImg(asset.key, el)"
              @load="markLoaded(asset.key)"
              @error="markLoaded(asset.key)"
            />
          </picture>
        </span>
      </TransitionGroup>

      <!-- Unlocked over a gallery nobody has published yet: a real state,
           and the studio's link is already out there, so it says so. -->
      <p v-if="!shown.length" class="text-[12.5px] leading-[1.55] font-normal text-muted-foreground mt-[9px] px-8 py-16">
        {{ locked ? $t('gallery.locked') : assets?.length ? $t('gallery.listEmpty') : $t('gallery.notPublished') }}
      </p>

      <GalleryLightbox
        v-model="openAt"
        :gallery-id="gallery.id"
        :title="gallery.title"
        :assets="shown"
        :lists="lists"
        :offers="offers"
        :share-url="shareUrl"
        :unhiding="active === HIDDEN"
      />

      <GalleryIntro v-if="introOpen" :rows="introLines" @done="introDone" />

      <!-- The couple's own half of their gallery, under the pictures. A
           guest never receives any of it. -->
      <section
        v-if="offers.lists && lists"
        class="mx-auto grid max-w-[1240px] grid-cols-[minmax(0,1fr)] gap-16 border-t border-border px-8 py-16 max-[760px]:px-4 min-[1100px]:grid-cols-[minmax(0,1fr)_320px] [&>div:not(:first-child)]:mt-11"
      >
        <div v-if="hasFavourites">
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
                <HoldToConfirm variant="ghost" class="hover:text-destructive!" @confirm="remove(list)">
                  {{ $t('common.remove') }}
                </HoldToConfirm>
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

          <form class="mt-8 grid gap-3.5 min-[761px]:flex min-[761px]:items-end" @submit.prevent="addList">
            <Label class="block flex-1">
              <span class="mb-2 block text-[13px] leading-none text-foreground">{{ $t('gallery.newListName') }}</span>
              <Input v-model="listForm.name" type="text" />
            </Label>
            <Button class="min-h-11 w-full justify-center disabled:opacity-60 min-[761px]:w-auto" type="submit" variant="portal-ghost" :disabled="!listForm.name">{{ $t('gallery.newList') }}</Button>
          </form>
        </div>

        <div>
          <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('gallery.shareTitle') }}</p>
          <div
            class="flex items-center gap-3.5 rounded-md border border-border p-3.5 [&_button]:cursor-pointer [&_button]:border-0 [&_button]:bg-none [&_button]:p-0 [&_button]:text-[12.5px] [&_button]:leading-none [&_button]:font-medium [&_button]:text-action [&_code]:min-w-0 [&_code]:flex-1 [&_code]:overflow-hidden [&_code]:text-[12.5px] [&_code]:text-ellipsis [&_code]:whitespace-nowrap [&_code]:text-muted-foreground"
          >
            <code>{{ gallery.url }}</code>
            <CopyButton :value="gallery.url" variant="ghost" />
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
            <HoldToConfirm
              v-if="gates?.[gate.on]"
              variant="ghost"
              class="hover:text-destructive!"
              @confirm="clearGate(gate.key)"
            >
              {{ $t('gallery.gateClear') }}
            </HoldToConfirm>
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

          <p v-if="guests" class="mt-5 text-[13.5px] leading-none tabular-nums text-muted-foreground">
            {{ $t('gallery.guestsLine', { views: $t('gallery.views', guests.views), marks: $t('gallery.marks', guests.marks), comments: $t('gallery.comments', guests.comments) }) }}
          </p>
        </div>
      </section>

      <footer
        class="mx-auto flex max-w-[1240px] flex-wrap justify-between gap-8 border-t border-border px-8 pt-11 pb-16 max-[760px]:px-4 text-[12.5px] text-muted-foreground"
      >
        <span>{{ studioName }}</span>
        <a href="/impressum">{{ $t('portal.footerImpressum') }}</a>
      </footer>
    </template>
  </div>
</template>

<style scoped>
.tile-img {
  filter: blur(12px);
  transform: scale(1.04);
  transition:
    opacity 650ms cubic-bezier(0.23, 1, 0.32, 1),
    filter 650ms cubic-bezier(0.23, 1, 0.32, 1),
    transform 1.2s cubic-bezier(0.22, 0.61, 0.36, 1);
}

[data-loaded='true'] .tile-img {
  filter: none;
  transform: none;
}

[data-loaded='true']:hover .tile-img {
  transform: scale(1.03);
}

.tile-move {
  transition: transform 400ms cubic-bezier(0.23, 1, 0.32, 1);
}

.tile-enter-active {
  transition:
    opacity 200ms cubic-bezier(0.23, 1, 0.32, 1),
    scale 200ms cubic-bezier(0.23, 1, 0.32, 1);
}

.tile-enter-from {
  opacity: 0;
  scale: 0.97;
}

@media (prefers-reduced-motion: reduce) {
  .tile-img,
  .tile-move,
  .tile-enter-active {
    transition: none;
  }

  .tile-img {
    filter: none;
    transform: none;
  }
}
</style>
