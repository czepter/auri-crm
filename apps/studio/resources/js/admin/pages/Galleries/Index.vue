<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import GallerySettings from '@/admin/components/gallery/GallerySettings.vue'
import Chip, { type ChipTone } from '@/ui/page/Chip.vue'
import { Button } from '@/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { ChoiceGroup } from '@/ui/choice-group'
import { useLiveProps } from '@/admin/lib/liveReload'
import { filterGalleries, loadView, saveView, type GalleryFilter } from '@/admin/lib/galleryFilter'

const props = defineProps<{
  galleries: App.Gallery.Data.GalleryRowData[]
  settings: App.Settings.Data.GallerySettingsData | null
  presets: App.Gallery.Data.GalleryPresetData[]
  mailTemplates: { id: string; name: string }[]
  galleryHost: string
}>()

useLiveProps(['galleries', 'settings', 'presets', 'mailTemplates'])

const { t } = useI18n()

const filter = ref<GalleryFilter>('all')
const view = ref(loadView())
watch(view, saveView)
const projectType = ref('all')
const shown = computed(() => filterGalleries(props.galleries, filter.value, new Date(), projectType.value))

const projectTypes = computed(() => [...new Map(
  props.galleries.flatMap((row) => (row.projectType === null ? [] : [[row.projectType, row.projectTypeLabel ?? row.projectType] as [string, string]])),
)])

const filters = computed(() => (['all', 'draft', 'prerelease', 'published', 'expiring'] as const).map((value) => ({
  value,
  label: value === 'all' ? t('galleries.filterAll') : value === 'expiring' ? t('galleries.filterExpiring') : t(`project.galleryState.${value}`),
})))

const views = computed(() => [
  { value: 'list', label: t('galleries.viewList') },
  { value: 'grid', label: t('galleries.viewGrid') },
])

const TONE: Record<string, ChipTone> = { draft: 'neutral', prerelease: 'info', published: 'success', withdrawn: 'neutral', expired: 'warn' }

// G-14: der Abschnitt steht in der Adresse, damit die alten Einstellungspfade
// hierher zeigen können. Ohne ihn bleibt der Raum zu.
const query = new URLSearchParams(window.location.search)
const section = query.get('einstellungen')
const settingsOpen = ref(props.settings !== null && section !== null)

function close() {
  settingsOpen.value = false
  window.history.replaceState({}, '', '/galerien')
}

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'
}
</script>

<template>
  <AdminLayout active="galerien">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('galleries.title')">
          <Button v-if="settings" class="ml-auto" type="button" variant="pill-muted" @click="settingsOpen = true">
            {{ $t('stages.entry') }}
          </Button>
          <Button :as="Link" variant="pill" :class="settings ? undefined : 'ml-auto'" href="/galerien/neu">{{ $t('galleries.create') }}</Button>
        </PageHead>

        <Empty v-if="!galleries.length" :text="$t('galleries.empty')">
          <Button :as="Link" variant="pill" href="/galerien/neu">{{ $t('galleries.create') }}</Button>
        </Empty>

        <div v-else class="px-6 pt-7 pb-8 max-[640px]:px-5">
          <div class="flex flex-wrap items-center gap-3 pb-5">
            <ChoiceGroup v-model="filter" :options="filters" />
            <Select v-if="projectTypes.length > 1" v-model="projectType">
              <SelectTrigger class="w-auto min-w-40" :aria-label="$t('galleries.projectTypeField')"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{{ $t('galleries.filterAllTypes') }}</SelectItem>
                <SelectItem v-for="[key, label] in projectTypes" :key="key" :value="key">{{ label }}</SelectItem>
              </SelectContent>
            </Select>
            <ChoiceGroup v-model="view" class="ml-auto" variant="segmented" :options="views" />
          </div>

          <div v-if="view === 'list'">
            <div class="flex gap-3.5 pb-2 text-small font-semibold text-ink-2 max-[640px]:hidden">
              <span class="w-16 flex-none" />
              <span class="min-w-0 flex-1">{{ $t('galleries.titleField') }}</span>
              <span class="w-14 flex-none text-right">{{ $t('galleries.imagesField') }}</span>
              <span class="w-28 flex-none">{{ $t('galleries.statusField') }}</span>
              <span class="w-20 flex-none">{{ $t('galleries.downloadField') }}</span>
              <span class="w-20 flex-none text-right">{{ $t('galleries.versionField') }}</span>
              <span class="w-24 flex-none text-right">{{ $t('galleries.expiryField') }}</span>
            </div>
            <div
              v-for="gallery in shown"
              :key="gallery.id"
              class="flex items-center gap-3.5 py-3 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none max-[640px]:flex-wrap max-[640px]:gap-x-3 max-[640px]:gap-y-1"
            >
              <img v-if="gallery.cover" :src="gallery.cover" alt="" class="h-12 w-16 flex-none rounded-[var(--r-sm)] bg-sunk object-cover max-[640px]:hidden" loading="lazy" @error="(event: Event) => (event.target as HTMLElement).classList.add('invisible')">
              <span v-else class="h-12 w-16 flex-none rounded-[var(--r-sm)] bg-sunk max-[640px]:hidden" />
              <!-- Unter 640px bleiben vom Titel sonst 110px: feste Spalten und
                   der Rand nehmen den Rest. Der Name bekommt die Zeile, die
                   Angaben rücken darunter nach links. -->
              <span class="min-w-0 flex-1 text-ui leading-[1.47] max-[640px]:w-full max-[640px]:flex-none">
                <Link :href="`/galerien/${gallery.id}`">{{ gallery.title ?? '—' }}</Link>
                <span v-if="gallery.projectName" class="text-small text-ink-2"> · {{ gallery.projectName }}</span>
              </span>
              <span class="w-14 flex-none text-right max-[640px]:w-auto max-[640px]:text-left text-small tabular-nums text-ink-2">{{ gallery.assetCount }}</span>
              <span class="w-28 flex-none max-[640px]:w-auto"><Chip :tone="TONE[gallery.status] ?? 'neutral'">{{ $t(`project.galleryState.${gallery.status}`) }}</Chip></span>
              <span class="w-20 flex-none text-small text-ink-2 max-[640px]:w-auto">{{ $t(`galleries.download.${gallery.downloadRule}`) }}</span>
              <span class="w-20 flex-none text-right text-small tabular-nums text-ink-2 max-[640px]:w-auto max-[640px]:text-left">
                {{ gallery.version ? $t('galleryRecord.version', { version: gallery.version }) : '—' }}
              </span>
              <span class="w-24 flex-none text-right text-small tabular-nums text-ink-2 max-[640px]:w-auto max-[640px]:text-left">{{ day(gallery.expiresAt) }}</span>
            </div>
          </div>

          <div v-else class="grid grid-cols-4 gap-5 max-[1100px]:grid-cols-3 max-[800px]:grid-cols-2 max-[480px]:grid-cols-1">
            <Link v-for="gallery in shown" :key="gallery.id" :href="`/galerien/${gallery.id}`" class="group block">
              <span class="relative block aspect-[4/3] overflow-hidden rounded-[var(--r-sm)] bg-sunk">
                <img v-if="gallery.cover" :src="gallery.cover" alt="" class="size-full object-cover" loading="lazy" @error="(event: Event) => (event.target as HTMLElement).classList.add('invisible')">
                <Chip class="absolute top-2.5 left-2.5 bg-paper" :tone="TONE[gallery.status] ?? 'neutral'">{{ $t(`project.galleryState.${gallery.status}`) }}</Chip>
              </span>
              <span class="mt-2 block text-ui font-medium">{{ gallery.title ?? '—' }}</span>
              <span class="block text-small text-ink-2">{{ gallery.assetCount }} · {{ gallery.projectName ?? '—' }}</span>
            </Link>
          </div>
        </div>

        <GallerySettings
          v-if="settingsOpen && settings"
          :gallery-host="galleryHost"
          :presets="presets"
          :mail-templates="mailTemplates"
          :preset-id="query.get('vorlage')"
          :preview-title="galleries[0]?.title ?? $page.props.brand"
          :section="section === 'gestaltung' || section === 'vorlagen' || section === 'startseite' ? section : 'standardwerte'"
          :settings="settings"
          @close="close"
        />
      </div>
    </Page>
  </AdminLayout>
</template>
