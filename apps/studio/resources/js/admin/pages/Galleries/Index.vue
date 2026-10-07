<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import GallerySettings from '@/admin/components/GallerySettings.vue'
import { Button } from '@/ui/button'

const props = defineProps<{
  galleries: App.Gallery.Data.GalleryRowData[]
  settings: App.Settings.Data.GallerySettingsData | null
  presets: App.Gallery.Data.GalleryPresetData[]
  galleryHost: string
}>()

// G-14: der Abschnitt steht in der Adresse, damit die alten Einstellungspfade
// hierher zeigen können. Ohne ihn bleibt der Raum zu.
const section = new URLSearchParams(window.location.search).get('einstellungen')
const settingsOpen = ref(props.settings !== null && section !== null)

function close() {
  settingsOpen.value = false
  window.history.replaceState({}, '', '/galerien')
}

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'
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

        <Empty v-if="!galleries.length" class="mx-8 mt-7 max-[640px]:mx-5" :text="$t('galleries.empty')">
          <Button :as="Link" variant="pill" href="/galerien/neu">{{ $t('galleries.create') }}</Button>
        </Empty>

        <div v-else class="max-w-[900px] px-8 pt-7 pb-8 max-[640px]:px-5">
          <div
            v-for="gallery in galleries"
            :key="gallery.id"
            class="flex items-baseline gap-3.5 py-3 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none max-[640px]:flex-wrap max-[640px]:gap-x-3 max-[640px]:gap-y-1"
          >
            <!-- Unter 640px bleiben vom Titel sonst 110px: drei feste Spalten
                 und der Rand nehmen den Rest. Der Name bekommt die Zeile, die
                 drei Angaben rücken darunter nach links. -->
            <span class="min-w-0 flex-1 text-ui leading-[1.47] max-[640px]:w-full max-[640px]:flex-none">
              <Link :href="`/galerien/${gallery.id}`">{{ gallery.title ?? '—' }}</Link>
              <span v-if="gallery.projectName" class="text-small text-ink-2"> · {{ gallery.projectName }}</span>
            </span>
            <span class="w-14 flex-none text-right max-[640px]:w-auto max-[640px]:text-left text-small tabular-nums text-ink-2">{{ gallery.assetCount }}</span>
            <span class="w-20 flex-none text-right text-small tabular-nums text-ink-2 max-[640px]:w-auto max-[640px]:text-left">
              {{ gallery.version ? $t('galleryRecord.version', { version: gallery.version }) : '—' }}
            </span>
            <span class="w-20 flex-none text-right text-small tabular-nums text-ink-2 max-[640px]:w-auto max-[640px]:text-left">{{ day(gallery.expiresAt) }}</span>
          </div>
        </div>

        <GallerySettings
          v-if="settingsOpen && settings"
          :gallery-host="galleryHost"
          :presets="presets"
          :preview-title="galleries[0]?.title ?? $page.props.brand"
          :section="section === 'gestaltung' || section === 'vorlagen' ? section : 'standardwerte'"
          :settings="settings"
          @close="close"
        />
      </div>
    </Page>
  </AdminLayout>
</template>
