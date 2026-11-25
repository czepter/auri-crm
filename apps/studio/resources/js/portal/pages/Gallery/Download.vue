<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import PortalBack from '@/portal/components/PortalBack.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { dayDate } from '@/portal/lib/dates'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { RadioGroup, RadioGroupItem } from '@/ui/radio-group'

type Selection = { id: string; name: string | null; count: number; sizes: Record<string, number> }
type Part = { number: number; bytes: number; url: string }
type Build = {
  id: string
  list: string
  listName: string | null
  size: string
  status: 'building' | 'ready' | 'failed'
  partCount: number
  parts: Part[]
}

const props = defineProps<{
  gallery: { id: string; title: string | null; count: number; expiresAt: string | null }
  step: 'pin' | 'email' | 'ready'
  gates: { key: string; value: string | null }[] | null
  selections: Selection[]
  builds: Build[]
}>()

const status = computed(() => usePage().props.flash.status)

const pinForm = useForm({ pin: '' })
const emailForm = useForm({ email: '' })

function submitPin() {
  pinForm.post(`/galerien/${props.gallery.id}/download/pin`)
}

function submitEmail() {
  emailForm.post(`/galerien/${props.gallery.id}/download/email`, { onSuccess: () => emailForm.reset() })
}

// G-03 (#231): what and how big. Both are choices before the archive exists,
// which is why the size sits on every line — nobody should start a
// four-gigabyte transfer to find out how big it was.
const chosenList = ref(props.selections[0]?.id ?? '')
const chosenSize = ref('original')

const selection = computed(() => props.selections.find((s) => s.id === chosenList.value) ?? null)

const build = computed(() =>
  props.builds.find((b) => b.list === chosenList.value && b.size === chosenSize.value) ?? null,
)

const prepareForm = useForm({})

function prepare() {
  prepareForm
    .transform(() => ({ list: chosenList.value || null, size: chosenSize.value }))
    .post(`/galerien/${props.gallery.id}/download/vorbereiten`, { preserveScroll: true })
}

// A build that is still going does not report progress — nothing measures
// it — so the screen re-asks rather than pretending to know how far along
// it is.
let poll: number | undefined
watch(
  () => props.builds.some((b) => b.status === 'building'),
  (building) => {
    window.clearInterval(poll)

    if (building) {
      poll = window.setInterval(() => router.reload({ only: ['builds'] }), 4000)
    }
  },
  { immediate: true },
)
onUnmounted(() => window.clearInterval(poll))

/**
 * Bytes as a studio's client would read them, not as a machine writes them.
 * Zero is a dash rather than "1 KB": a gallery whose assets never got a WebP
 * derivative has nothing at that size, and rounding it up to a plausible
 * number is the screen telling a lie it will be caught in.
 */
function size(bytes: number) {
  if (bytes <= 0) return '—'
  if (bytes >= 1_000_000_000) return `${(bytes / 1_000_000_000).toFixed(1).replace('.', ',')} GB`
  if (bytes >= 1_000_000) return `${Math.round(bytes / 1_000_000)} MB`

  return `${Math.max(1, Math.round(bytes / 1000))} KB`
}

/** Nothing to pack at this size means nothing to offer at it. */
function available(option: string) {
  return (selection.value?.sizes[option] ?? 0) > 0
}

const sizeOptions = computed(() => (['original', 'web'] as const).filter(available))

watch(sizeOptions, (options) => {
  if (options.length === 1) chosenSize.value = options[0]
}, { immediate: true })

/* gallery.css, inlined. Its own .field / .hint / .err are not the portal's of
   the same name — the sheet scopes every rule under [data-theme="gallery"]. */
const INPUT = [
  '[&_input]:w-full [&_input]:rounded-md [&_input]:border [&_input]:border-input',
  '[&_input]:bg-muted [&_input]:px-3.5 [&_input]:py-[13px]',
  '[&_input]:text-[15px] [&_input]:leading-[1.2] [&_input]:font-normal [&_input]:text-foreground',
  '[&_input::placeholder]:text-faint',
].join(' ')
const OPT = [
  'flex cursor-pointer items-center gap-5 border-b border-border py-5',
  '[&>span]:flex-1 [&>span:last-child]:flex-none [&>span:last-child]:text-right',
  '[&_b]:text-ui [&_b]:leading-[1.3] [&_b]:font-semibold [&_b]:text-foreground [&_b]:[display:block]',
  '[&_b+span]:text-[12.5px] [&_b+span]:leading-[1.4] [&_b+span]:font-normal [&_b+span]:text-muted-foreground',
].join(' ')
</script>

<template>
  <PortalShell>
    <PortalHeader />

    <PortalWrap class="pt-[var(--band)] pb-28">
    <!-- mockup 11: the whole page centers on the pin step -->
    <div v-if="step === 'pin'" class="mx-auto max-w-[560px] text-center">
      <div class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('gallery.pinKicker') }}</div>
      <h1 class="m-0 font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">{{ $t('gallery.pinTitle') }}</h1>

      <form @submit.prevent="submitPin">
        <Label class="mt-8 text-left [display:block]" :class="[INPUT, '[&_input]:text-center [&_input]:indent-[0.55em] [&_input]:text-[21px] [&_input]:tracking-[0.55em] [&_input]:tabular-nums']">
          <span class="mb-2 text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint [display:block]">{{ $t('gallery.pinLabel') }}</span>
          <Input v-model="pinForm.pin" type="text" inputmode="numeric" autocomplete="off" required />
        </Label>

        <Button class="mt-5 ml-0 w-full justify-center" variant="portal" type="submit" :disabled="pinForm.processing">
          {{ $t('gallery.pinContinue') }}
        </Button>
      </form>

      <div v-if="pinForm.errors.pin" class="mt-5 rounded-md border border-destructive/30 bg-due-bg px-3.5 py-3 text-left text-small leading-[1.55] font-normal text-destructive"><b>{{ pinForm.errors.pin }}</b></div>

      <Button as="a" variant="portal-ghost" class="mt-3 w-full justify-center" :href="`/galerien/${gallery.id}`">
        {{ $t('gallery.backToGallery') }}
      </Button>
    </div>

    <!-- mockup 12: only the header centers, the field and actions stay left -->
    <div v-else-if="step === 'email'" class="mx-auto max-w-[560px]">
      <div class="text-center">
        <div class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('gallery.emailKicker') }}</div>
        <h1 class="m-0 font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">{{ $t('gallery.emailTitle') }}</h1>
        <p v-if="gallery.title" class="mt-1.5 text-ui leading-[1.6] font-normal text-muted-foreground">{{ gallery.title }}</p>
      </div>

      <p v-if="status" class="mt-[9px] text-[12.5px] leading-[1.55] font-normal text-muted-foreground">{{ status }}</p>

      <form v-else @submit.prevent="submitEmail">
        <Label class="mt-8 text-left [display:block]" :class="INPUT">
          <span class="mb-2 text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint [display:block]">{{ $t('gallery.emailLabel') }}</span>
          <Input v-model="emailForm.email" type="email" autocomplete="email" required />
        </Label>

        <Button class="mt-5 ml-0 w-full justify-center" variant="portal" type="submit" :disabled="emailForm.processing">
          {{ $t('gallery.emailRequest') }}
        </Button>
      </form>

      <Button as="a" variant="portal-ghost" class="mt-3 w-full justify-center" :href="`/galerien/${gallery.id}`">
        {{ $t('gallery.backToGallery') }}
      </Button>
    </div>

    <!-- G-03 (#231), mockup galerie-download.html: both gates cleared, so
         the screen finally offers something instead of saying "Alles bereit"
         and stopping there. -->
    <!-- .dlcol asked for var(--s8) at the bottom, which is declared nowhere:
         the whole shorthand was invalid, so the column has never had padding. -->
    <div v-else class="mx-auto max-w-[620px]">
      <PortalBack :href="`/galerien/${gallery.id}`" :label="$t('gallery.backToGallery')" />

      <h1 class="m-0 font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)] mt-8">{{ $t('gallery.downloadTitle') }}</h1>
      <p class="mt-1.5 text-ui leading-[1.6] font-normal text-muted-foreground">
        {{ [gallery.title, $t('portal.imageCount', gallery.count)].filter(Boolean).join(' · ') }}
        <template v-if="gallery.expiresAt">
          · {{ $t('gallery.onlineUntil', { date: dayDate(gallery.expiresAt) }) }}
        </template>
      </p>

      <!-- What was asked and answered, rather than forgotten the moment it
           passes. -->
      <div v-if="gates?.length" class="mt-8 flex flex-wrap gap-5">
        <span
          v-for="gate in gates"
          :key="gate.key"
          class="inline-flex items-center gap-[7px] text-[12px] leading-none font-medium text-ok [&_svg]:size-3.5 [&_svg]:flex-none [&_svg]:fill-none [&_svg]:stroke-current [&_svg]:stroke-2 [&_svg]:[stroke-linecap:round] [&_svg]:[stroke-linejoin:round]"
        >
          <svg viewBox="0 0 24 24"><path d="M4 12l6 6L20 6" /></svg>
          {{ gate.value ?? $t(`gallery.${gate.key}`) }}
        </span>
      </div>

      <section class="mt-16">
        <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('gallery.whatLabel') }}</p>
        <div v-if="selections.length === 1" :class="OPT">
          <span>
            <b>{{ selections[0].name ?? (selections[0].id ? $t('gallery.heart') : $t('gallery.everything')) }}</b>
            <span>{{ $t('portal.imageCount', selections[0].count) }}</span>
          </span>
          <span class="text-[12.5px] leading-none font-normal whitespace-nowrap tabular-nums text-muted-foreground">{{ size(selections[0].sizes[chosenSize] ?? 0) }}</span>
        </div>
        <RadioGroup v-else v-model="chosenList" class="gap-0">
          <label v-for="option in selections" :key="option.id" :class="OPT">
            <RadioGroupItem class="relative border-muted-foreground after:absolute after:-inset-3 after:content-['']" :value="option.id" />
            <span>
              <b>{{ option.name ?? (option.id ? $t('gallery.heart') : $t('gallery.everything')) }}</b>
              <span>{{ $t('portal.imageCount', option.count) }}</span>
            </span>
            <span class="text-[12.5px] leading-none font-normal whitespace-nowrap tabular-nums text-muted-foreground">{{ size(option.sizes[chosenSize] ?? 0) }}</span>
          </label>
        </RadioGroup>
      </section>

      <section class="mt-16">
        <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('gallery.sizeLabel') }}</p>
        <div v-if="sizeOptions.length === 1" :class="OPT">
          <span>
            <b>{{ $t(`gallery.size.${sizeOptions[0]}`) }}</b>
            <span>{{ $t(`gallery.sizeHint.${sizeOptions[0]}`) }}</span>
          </span>
          <span class="text-[12.5px] leading-none font-normal whitespace-nowrap tabular-nums text-muted-foreground">{{ size(selection?.sizes[sizeOptions[0]] ?? 0) }}</span>
        </div>
        <RadioGroup v-else v-model="chosenSize" class="gap-0">
          <!-- ZipBuildSize::Web is "Zum Teilen" to a client; the enum value
               stays what it is. de.json wins over any class name. -->
          <label
            v-for="option in (['original', 'web'] as const)"
            :key="option"
            :class="[OPT, !available(option) && 'cursor-default [&_b+span]:text-faint! [&_b]:text-faint!']"
          >
            <RadioGroupItem class="relative border-muted-foreground after:absolute after:-inset-3 after:content-['']" :value="option" :disabled="!available(option)" />
            <span>
              <b>{{ $t(`gallery.size.${option}`) }}</b>
              <span>{{ $t(`gallery.sizeHint.${option}`) }}</span>
            </span>
            <span class="text-[12.5px] leading-none font-normal whitespace-nowrap tabular-nums text-muted-foreground">{{ size(selection?.sizes[option] ?? 0) }}</span>
          </label>
        </RadioGroup>

        <Button
          type="button"
          variant="portal"
          class="mt-8"
          :state="prepareForm.processing ? 'loading' : undefined"
          :disabled="prepareForm.processing || build?.status === 'building' || !available(chosenSize)"
          @click="prepare"
        >
          {{ build?.status === 'building' ? $t('gallery.preparing') : $t('gallery.prepare') }}
        </Button>
      </section>

      <!-- There are parts, not a file: BuildGalleryZip plans volumes, and one
           button would be lying about 4,2 GB. -->
      <section v-if="build" class="mt-16">
        <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('gallery.yourFiles') }}</p>

        <p v-if="build.status === 'failed'" class="mt-5 rounded-md border border-destructive/30 bg-due-bg px-3.5 py-3 text-left text-small leading-[1.55] font-normal text-destructive"><b>{{ $t('gallery.buildFailed') }}</b></p>

        <p v-else-if="build.status === 'building'" class="mt-[9px] text-[12.5px] leading-[1.55] font-normal text-muted-foreground">{{ $t('gallery.preparing') }}</p>

        <div v-else class="flex flex-col">
          <div
            v-for="part in build.parts"
            :key="part.number"
            class="flex items-center gap-5 border-b border-border py-5 [&>span:first-child]:flex-1 [&_b]:text-[13.5px] [&_b]:leading-[1.3] [&_b]:font-semibold [&_b]:text-foreground"
          >
            <span>
              <b>{{ $t('gallery.partOf', { at: part.number, of: build.partCount }) }}</b>
            </span>
            <span class="text-[12.5px] leading-none font-normal whitespace-nowrap tabular-nums text-muted-foreground">{{ size(part.bytes) }}</span>
            <Button as="a" variant="portal-chip" :href="part.url">{{ $t('gallery.download') }}</Button>
          </div>
        </div>

        <p class="mt-[9px] text-[12.5px] leading-[1.55] font-normal text-muted-foreground">{{ $t('gallery.partsLive') }}</p>
      </section>
    </div>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
