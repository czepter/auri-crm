<script setup lang="ts">
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Input } from '@/ui/input'
import { ChoiceGroup } from '@/ui/choice-group'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'

const props = defineProps<{
  gallery: {
    id: string
    title: string | null
    url: string
    viewPassword: string | null
    downloadPin: string | null
    requiresVerifiedEmail: boolean
    expiresAt: string | null
    balanceSettled: boolean
    requiresSettledBalance: boolean
    design: Record<string, string> | null
    offers: { downloads: boolean; lists: boolean; comments: boolean }
  }
  siblings: { id: string; title: string | null; assetCount: number; open: boolean }[]
  figures: {
    published: number | null
    assets: number
    expiresAt: string | null
    marked: number
    downloaded: number
    viewed: number
  }
  sets: { name: string | null; count: number }[]
  assets: { key: string; set: string | null; src: string | null }[]
  versions: { version: number; publishedAt: string | null; assetCount: number }[]
  builds: { id: string; size: string; status: string; parts: number; bytes: number }[]
  lists: { id: string; name: string | null; owner: string | null; count: number }[]
  activity: {
    downloads: { who: string; what: string | null; size: string | null; on: string | null }[]
    marks: { who: string | null; what: string; on: string }[]
    comments: { who: string | null; what: string; on: string }[]
    views: { who: string | null; on: string }[]
  }
}>()

// C-19 (#255): five motions, five tabs. Four surfaces on one screen would not
// be management but a heap — and the pictures need the whole width.
const TABS = ['bilder', 'gestaltung', 'download', 'freigaben', 'aktivitaet'] as const
const tab = ref<(typeof TABS)[number]>('bilder')

// C-20 (#257): three sheets, because the questions differ — who took
// something, who marked something, who was here at all.
const SHEETS = ['downloads', 'marks', 'views'] as const
const sheet = ref<(typeof SHEETS)[number]>('downloads')

const activeSet = ref<string | null>(null)

const secrets = useForm({
  view_password: props.gallery.viewPassword ?? '',
  download_pin: props.gallery.downloadPin ?? '',
})

function saveSecrets() {
  secrets.transform((data) => ({
    view_password: data.view_password === '' ? null : data.view_password,
    download_pin: data.download_pin === '' ? null : data.download_pin,
  })).patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const design = useForm({ design: props.gallery.design ?? { cover: 'full', type: 'serif', colour: 'light', grid: 'wide' } })

function saveDesign() {
  design.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

const offers = useForm({
  downloads_enabled: props.gallery.offers.downloads,
  lists_enabled: props.gallery.offers.lists,
  comments_enabled: props.gallery.offers.comments,
})

function saveOffers() {
  offers.patch(`/galerien/${props.gallery.id}`, { preserveScroll: true })
}

// Der Weg, den man tatsaechlich geht: eine Galerie einrichten, bis sie sitzt,
// und dann festhalten, womit die naechste anfangen soll.
const savingPreset = ref(false)
const presetForm = useForm({ name: props.gallery.title ?? '' })

function saveAsPreset() {
  presetForm.post(`/galerien/${props.gallery.id}/vorlage`, {
    preserveScroll: true,
    onSuccess: () => (savingPreset.value = false),
  })
}

const COVERS = ['full', 'split', 'plain']
const COLOURS = ['light', 'dark', 'warm']
const GRIDS = ['wide', 'dense', 'single']

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'
}

function megabytes(bytes: number) {
  return `${Math.round(bytes / 1_000_000)} MB`
}

function shown() {
  return activeSet.value === null ? props.assets : props.assets.filter((asset) => asset.set === activeSet.value)
}
</script>

<template>
  <AdminLayout active="projekte">
    <div data-tokens>
      <PageHead :title="gallery.title ?? '—'" />

      <div class="max-w-none px-8 pt-7">
        <!-- Link, password and code stand in clear and are set here: both are
             handed to guests anyway, and a studio that cannot look one up
             sets a new one on every enquiry (C-17, #253). -->
        <div class="mb-5 flex items-start gap-[26px] pb-5 shadow-[inset_0_-1px_0_var(--rule)]">
          <div class="flex min-w-0 flex-1 flex-col gap-2">
            <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.link') }}</div>
            <div class="mt-1.5 text-[17px] leading-none font-semibold tracking-[-0.018em] tabular-nums"><a class="text-[17px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline" :href="gallery.url">{{ gallery.url }}</a></div>

            <div class="mt-2 flex gap-1.5">
              <Link
                v-for="sibling in siblings"
                :key="sibling.id"
                class="inline-flex items-baseline gap-1.5 rounded-full px-3 py-1.5 text-[13px] no-underline"
                :class="sibling.open ? 'bg-foreground text-paper' : 'bg-sunk text-foreground'"
                :href="`/galerien/${sibling.id}`"
              >
                {{ sibling.title ?? '—' }}<span
                  class="text-[11.5px]"
                  :class="sibling.open ? 'text-[#B9B9BD]' : 'text-ink-2'"
                >{{ sibling.assetCount }}</span>
              </Link>
            </div>
          </div>

          <div class="flex min-w-0 flex-col gap-2">
            <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('gallery.gateView') }}</div>
            <Input v-model="secrets.view_password" class="w-[190px]" type="text" @blur="saveSecrets" />
          </div>

          <div class="flex min-w-0 flex-col gap-2">
            <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('gallery.gateDownload') }}</div>
            <Input v-model="secrets.download_pin" class="w-[110px]" type="text" @blur="saveSecrets" />
          </div>
        </div>

        <div class="mb-5 flex">
          <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
            <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.published') }}</div>
            <div class="mt-[5px] text-[17px] leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums">{{ figures.published ? $t('galleryRecord.version', { version: figures.published }) : '—' }}</div>
          </div>
          <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
            <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.images') }}</div>
            <div class="mt-[5px] text-[17px] leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums">{{ figures.assets }}</div>
          </div>
          <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
            <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.expiry') }}</div>
            <div class="mt-[5px] text-[15px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ day(figures.expiresAt) }}</div>
          </div>
          <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
            <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.marked') }}</div>
            <div class="mt-[5px] text-[17px] leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums">{{ figures.marked }}</div>
          </div>
          <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
            <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.downloaded') }}</div>
            <div class="mt-[5px] text-[17px] leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums">{{ figures.downloaded }}</div>
          </div>
          <div class="min-w-0 flex-1 px-[18px] shadow-[inset_1px_0_0_var(--rule)] first:pl-0 first:shadow-none">
            <div class="text-[10.5px] tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.viewed') }}</div>
            <div class="mt-[5px] text-[17px] leading-[1.2] font-semibold tracking-[-0.02em] tabular-nums">{{ figures.viewed }}</div>
          </div>
        </div>

        <!-- balance_settled is a state out of the payments, never a switch —
             it stands as a value beside the block it causes. -->
        <p v-if="gallery.requiresSettledBalance && !gallery.balanceSettled" class="text-[11.5px] leading-[1.45] text-faint">
          {{ $t('galleryRecord.blockedByBalance') }}
        </p>
      </div>

      <div class="mt-4 mb-5 flex gap-5 px-8 shadow-[inset_0_-1px_0_var(--rule)]">
        <button
          v-for="name in TABS"
          :key="name"
          class="cursor-pointer border-0 bg-transparent pt-2.5 pb-[11px] text-[13px] leading-[1.4]"
          :class="tab === name
            ? 'font-semibold text-foreground shadow-[inset_0_-2px_0_var(--ink)]'
            : 'font-normal text-ink-2 hover:text-foreground'"
          type="button"
          @click="tab = name"
        >
          {{ $t(`galleryRecord.tab.${name}`) }}
        </button>
      </div>

      <!-- Bilder: sets on the left, grid on the right, versions underneath —
           a version is a published selection, so it is a statement about
           pictures and belongs here, not with the permissions. -->
      <div v-if="tab === 'bilder'" class="flex">
        <div class="w-[220px] flex-none pt-5 pr-4 pb-7 pl-8">
          <a class="mb-0.5 flex items-baseline gap-2 rounded-lg px-2.5 py-2 text-[13px] text-foreground no-underline" :class="activeSet === null && 'bg-sunk-2 font-semibold'" href="#" @click.prevent="activeSet = null">
            {{ $t('gallery.all') }}<span class="ml-auto text-[11.5px] tabular-nums text-ink-2">{{ figures.assets }}</span>
          </a>
          <a
            v-for="set in sets.filter((entry) => entry.name !== null)"
            :key="set.name!"
            href="#"
            class="mb-0.5 flex items-baseline gap-2 rounded-lg px-2.5 py-2 text-[13px] text-foreground no-underline"
            :class="activeSet === set.name && 'bg-sunk-2 font-semibold'"
            @click.prevent="activeSet = set.name"
          >
            {{ set.name }}<span class="ml-auto text-[11.5px] tabular-nums text-ink-2">{{ set.count }}</span>
          </a>
        </div>

        <div class="min-w-0 flex-1 pt-5 pr-8 pb-7">
          <div class="mb-4 flex items-center gap-3">
            <h2 class="m-0 text-[17px] leading-[1.2] font-semibold tracking-[-0.022em]">{{ $t('galleryRecord.images') }}</h2>
            <span class="text-[12px] text-ink-2">{{ shown().length }}</span>
            <Link class="ml-auto flex cursor-pointer items-center gap-1.5 rounded-full border-0 bg-sunk-2 px-3.5 py-1.5 text-[12.5px] font-medium tracking-[-0.01em] whitespace-nowrap text-foreground no-underline transition-[filter,background] duration-[120ms] ease-out hover:bg-[#E9E9EC]" :href="`/galerien/${gallery.id}/medien`">
              {{ $t('galleryRecord.ingest') }}
            </Link>
          </div>

          <div class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-2.5">
            <figure v-for="asset in shown()" :key="asset.key" class="relative m-0">
              <img v-if="asset.src" class="block aspect-3/2 w-full rounded-lg object-cover" :src="`${asset.src}?w=400&fmt=jpeg`" alt="" loading="lazy" />
              <span v-if="asset.set" class="absolute top-[7px] left-[7px] flex gap-1 text-[10.5px] text-paper"><span class="rounded-full bg-[rgba(29,29,31,0.62)] px-[7px] py-0.5">{{ asset.set }}</span></span>
            </figure>
          </div>

          <h2 class="mt-7">{{ $t('galleryRecord.versions') }}</h2>
          <div>
            <div v-for="version in versions" :key="version.version" class="flex items-baseline gap-[9px] py-2 shadow-[inset_0_1px_0_#EEEEF0] first:shadow-none">
              <span class="w-[62px] flex-none text-[12px] font-semibold tracking-[-0.01em] tabular-nums">{{ $t('galleryRecord.version', { version: version.version }) }}</span>
              <span class="text-[13px] font-medium tracking-[-0.01em]">{{ $t('contacts.assetCount', version.assetCount) }}</span>
              <span class="ml-auto text-[11.5px] whitespace-nowrap text-ink-2">{{ day(version.publishedAt) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Gestaltung: choosing without seeing is guessing, so the choice is on
           the left and the result on the right. -->
      <div v-else-if="tab === 'gestaltung'" class="flex">
        <div class="w-80 flex-none pt-5 pr-4 pb-7 pl-8">
          <div class="mb-4 flex flex-col gap-1">
            <span class="pl-0.5 text-[10.5px] leading-none font-normal tracking-[0.05em] uppercase text-ink-2">{{ $t('galleryRecord.cover') }}</span>
            <ChoiceGroup
              v-model="design.design.cover"
              :options="COVERS.map((option) => ({ value: option, label: $t(`galleryRecord.cover_${option}`) }))"
              @update:model-value="saveDesign"
            />
          </div>

          <div class="mb-4 flex flex-col gap-1">
            <span class="pl-0.5 text-[10.5px] leading-none font-normal tracking-[0.05em] uppercase text-ink-2">{{ $t('galleryRecord.colour') }}</span>
            <ChoiceGroup
              v-model="design.design.colour"
              :options="COLOURS.map((option) => ({ value: option, label: $t(`galleryRecord.colour_${option}`) }))"
              @update:model-value="saveDesign"
            />
          </div>

          <div class="flex flex-col gap-1">
            <span class="pl-0.5 text-[10.5px] leading-none font-normal tracking-[0.05em] uppercase text-ink-2">{{ $t('galleryRecord.grid') }}</span>
            <ChoiceGroup
              v-model="design.design.grid"
              :options="GRIDS.map((option) => ({ value: option, label: $t(`galleryRecord.grid_${option}`) }))"
              @update:model-value="saveDesign"
            />
          </div>
        </div>

        <div class="min-w-0 flex-1 bg-sunk px-8 pt-5 pb-7">
          <div class="overflow-hidden rounded-[12px] bg-paper">
            <div class="relative">
              <img v-if="assets[0]?.src" class="block aspect-video w-full object-cover" :src="`${assets[0].src}?w=1200&fmt=jpeg`" alt="" />
              <div class="absolute inset-x-0 bottom-0 bg-[linear-gradient(transparent,rgba(20,16,14,0.55))] px-5 py-[22px] text-center text-paper">
                <b class="block text-[19px] leading-[1.2] font-semibold tracking-[0.04em] uppercase">{{ gallery.title }}</b>
                <span class="text-[11.5px] opacity-85">{{ $t('contacts.assetCount', figures.assets) }}</span>
              </div>
            </div>
            <div class="grid grid-cols-3 gap-1.5 p-2.5">
              <img
                v-for="asset in assets.slice(1, 7)"
                :key="asset.key"
                class="block aspect-3/2 w-full rounded-[3px] object-cover"
                :src="`${asset.src}?w=400&fmt=jpeg`"
                alt=""
                loading="lazy"
              />
            </div>
          </div>

          <div class="mt-4 flex items-center gap-3">
            <Dialog v-model:open="savingPreset">
              <DialogTrigger as-child>
                <Button type="button" variant="pill-muted">{{ $t('galleryRecord.saveAsPreset') }}</Button>
              </DialogTrigger>

              <DialogContent>
                <DialogHeader>
                  <DialogTitle>{{ $t('galleryRecord.saveAsPreset') }}</DialogTitle>
                </DialogHeader>

                <DialogBody>
                  <form id="gallery-preset-form" class="flex flex-col gap-1.5" @submit.prevent="saveAsPreset">
                    <Label for="preset-name">{{ $t('galleryPresets.nameField') }}</Label>
                    <Input id="preset-name" v-model="presetForm.name" type="text" maxlength="120" />
                    <p v-if="presetForm.errors.name" class="text-[11.5px] leading-[1.45] text-destructive">{{ presetForm.errors.name }}</p>
                  </form>
                </DialogBody>

                <DialogFooter>
                  <Button type="submit" form="gallery-preset-form" :disabled="presetForm.processing || presetForm.name === ''">
                    {{ $t('common.save') }}
                  </Button>
                  <DialogClose as-child>
                    <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
                  </DialogClose>
                </DialogFooter>
              </DialogContent>
            </Dialog>

            <a class="ml-auto text-[12.5px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline" :href="gallery.url">{{ $t('galleryRecord.openLive') }}</a>
          </div>
        </div>
      </div>

      <!-- Download: the archives are the fact, so the tab shows them rather
           than a promise about them. -->
      <div v-else-if="tab === 'download'" class="max-w-[760px] px-8 pt-7 pb-8">
        <table v-if="builds.length" class="w-full border-collapse">
          <thead>
            <tr>
              <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('gallery.sizeLabel') }}</th>
              <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.parts') }}</th>
              <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2 text-right">{{ $t('galleryRecord.size') }}</th>
              <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.state') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="build in builds" :key="build.id">
              <td class="px-8 py-2.5 align-baseline text-[13.5px] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]">{{ $t(`gallery.size.${build.size}`) }}</td>
              <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">{{ build.parts }}</td>
              <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums">{{ megabytes(build.bytes) }}</td>
              <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">{{ build.status }}</td>
            </tr>
          </tbody>
        </table>

        <p v-else class="text-[11.5px] leading-[1.45] text-faint">{{ $t('galleryRecord.noBuilds') }}</p>
      </div>

      <!-- Freigaben: lists carry their owner, because a list always belongs
           to somebody — the couple or a guest who identified themselves. -->
      <div v-else-if="tab === 'freigaben'" class="max-w-[760px] px-8 pt-7 pb-8">
        <div class="flex items-center gap-4 py-[11px] shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
          <div class="min-w-0 flex-1 text-[13.5px]">
            {{ $t('gallery.gateView') }}
            <div class="mt-0.5 text-[12px] text-ink-2">{{ gallery.viewPassword ?? $t('gallery.gateOff') }}</div>
          </div>
        </div>
        <div class="flex items-center gap-4 py-[11px] shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
          <div class="min-w-0 flex-1 text-[13.5px]">
            {{ $t('gallery.gateDownload') }}
            <div class="mt-0.5 text-[12px] text-ink-2">{{ gallery.downloadPin ?? $t('gallery.gateOff') }}</div>
          </div>
        </div>
        <div class="flex items-center gap-4 py-[11px] shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
          <div class="min-w-0 flex-1 text-[13.5px]">
            {{ $t('galleryRecord.emailGate') }}
            <div class="mt-0.5 text-[12px] text-ink-2">{{ gallery.requiresVerifiedEmail ? $t('gallery.gateOn') : $t('gallery.gateOff') }}</div>
          </div>
        </div>

        <div class="mt-7 flex flex-col gap-2.5">
          <div class="flex items-center gap-2.5">
            <Checkbox id="cb-offer-downloads" v-model="offers.downloads_enabled" @update:model-value="saveOffers" />
            <Label variant="inline" for="cb-offer-downloads">{{ $t('gallerySettings.downloadsLabel') }}</Label>
          </div>
          <div class="flex items-center gap-2.5">
            <Checkbox id="cb-offer-lists" v-model="offers.lists_enabled" @update:model-value="saveOffers" />
            <Label variant="inline" for="cb-offer-lists">{{ $t('gallerySettings.listsLabel') }}</Label>
          </div>
          <div class="flex items-center gap-2.5">
            <Checkbox id="cb-offer-comments" v-model="offers.comments_enabled" @update:model-value="saveOffers" />
            <Label variant="inline" for="cb-offer-comments">{{ $t('gallerySettings.commentsLabel') }}</Label>
          </div>
        </div>

        <h2 class="mt-7">{{ $t('gallery.listsTitle') }}</h2>
        <div>
          <div v-for="list in lists" :key="list.id" class="flex items-baseline gap-[9px] py-2 shadow-[inset_0_1px_0_#EEEEF0] first:shadow-none">
            <span class="text-[13px] font-medium tracking-[-0.01em]">{{ list.name ?? $t('gallery.heart') }}</span>
            <span class="w-14 flex-none text-[10.5px] tracking-[0.05em] uppercase text-ink-2">{{ list.owner ?? $t('galleryRecord.guest') }}</span>
            <span class="ml-auto text-[11.5px] whitespace-nowrap text-ink-2">{{ $t('contacts.assetCount', list.count) }}</span>
          </div>
          <p v-if="!lists.length" class="text-[11.5px] leading-[1.45] text-faint">{{ $t('gallery.listEmpty') }}</p>
        </div>
      </div>

      <div v-else class="max-w-[760px] px-8 pt-7 pb-8">
        <span class="flex rounded-lg bg-sunk-2 p-0.5">
          <button
            v-for="name in SHEETS"
            :key="name"
            class="cursor-pointer rounded-[6px] border-0 bg-transparent px-3.5 py-1 text-[12.5px] leading-[1.4] text-foreground transition-colors duration-[120ms] ease-out"
            :class="sheet === name ? 'bg-paper font-medium' : 'font-normal'"
            type="button"
            @click="sheet = name"
          >
            {{ $t(`galleryRecord.sheet.${name}`) }}
          </button>
        </span>

        <table class="mt-4 w-full border-collapse">
          <thead>
            <tr>
              <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.who') }}</th>
              <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2">{{ $t('galleryRecord.what') }}</th>
              <th class="px-8 pb-2 text-left text-[11px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2 text-right">{{ $t('galleryRecord.when') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="sheet === 'downloads'">
              <tr v-for="(row, index) in activity.downloads" :key="index">
                <td class="px-8 py-2.5 align-baseline text-[13.5px] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]">{{ row.who }}</td>
                <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">{{ row.what ?? $t('gallery.everything') }} · {{ $t(`gallery.size.${row.size}`) }}</td>
                <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums">{{ day(row.on) }}</td>
              </tr>
            </template>

            <template v-else-if="sheet === 'marks'">
              <tr v-for="(row, index) in activity.marks" :key="index">
                <td class="px-8 py-2.5 align-baseline text-[13.5px] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">{{ row.what }}</td>
                <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums">{{ day(row.on) }}</td>
              </tr>
              <tr v-for="(row, index) in activity.comments" :key="`c${index}`">
                <td class="px-8 py-2.5 align-baseline text-[13.5px] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">{{ row.what }}</td>
                <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums">{{ day(row.on) }}</td>
              </tr>
            </template>

            <template v-else>
              <tr v-for="(row, index) in activity.views" :key="index">
                <td class="px-8 py-2.5 align-baseline text-[13.5px] font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_#F0F0F2]">{{ row.who ?? $t('galleryRecord.guest') }}</td>
                <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-ink-2">—</td>
                <td class="px-8 py-2.5 align-baseline text-[13px] shadow-[inset_0_1px_0_#F0F0F2] text-right tabular-nums">{{ day(row.on) }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>
