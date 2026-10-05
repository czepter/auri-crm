<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'

type Archive = { id: string; status: 'building' | 'ready' | 'failed'; size: number | null; expiresAt: string | null; url: string | null }

const props = defineProps<{
  contacts: number
  projects: number
  archives: { daten: Archive | null; galleries: { id: string; title: string; size: number; archive: Archive | null }[] } | null
}>()

const { t } = useI18n()

const rows = computed(() => [
  { gallery: null, title: t('settings.exportData'), size: props.archives?.daten?.size ?? null, archive: props.archives?.daten ?? null },
  ...(props.archives?.galleries ?? []).map((gallery) => ({ gallery: gallery.id, title: gallery.title, size: gallery.size, archive: gallery.archive })),
])

function size(bytes: number) {
  const [value, unit] = bytes >= 1e9 ? [bytes / 1e9, 'GB'] : [bytes / 1e6, 'MB']

  return `${value.toLocaleString('de-DE', { maximumFractionDigits: 1 })} ${unit}`
}

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function create(gallery: string | null) {
  router.post('/einstellungen/export/archiv', { gallery }, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('settings.navExport')" />

        <div class="px-8 pt-7 pb-8">
          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="export" />

            <div class="max-w-[620px] min-w-0 flex-1">
              <div class="flex items-center gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]">
                <span class="flex-1 text-ui font-semibold">{{ $t('contacts.title') }}</span>
                <span class="text-small tabular-nums text-ink-2">{{ contacts }}</span>
                <Button as="a" variant="outline" href="/einstellungen/export/kontakte">CSV</Button>
              </div>
              <div class="flex items-center gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]">
                <span class="flex-1 text-ui font-semibold">{{ $t('projects.title') }}</span>
                <span class="text-small tabular-nums text-ink-2">{{ projects }}</span>
                <Button as="a" variant="outline" href="/einstellungen/export/projekte">CSV</Button>
              </div>

              <div v-if="archives" class="mt-8">
                <div
                  v-for="row in rows"
                  :key="row.gallery ?? 'daten'"
                  class="flex items-center gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]"
                >
                  <span class="min-w-0 flex-1 truncate text-ui font-semibold">{{ row.title }}</span>
                  <span v-if="row.size !== null" class="text-small tabular-nums text-ink-2">{{ size(row.size) }}</span>
                  <span v-if="row.archive?.status === 'building'" class="text-small text-ink-2">{{ $t('settings.exportBuilding') }}</span>
                  <template v-else-if="row.archive?.url">
                    <span class="text-small text-ink-2">{{ $t('settings.exportUntil', { date: day(row.archive.expiresAt!) }) }}</span>
                    <Button as="a" variant="outline" :href="row.archive.url">{{ $t('settings.exportDownload') }}</Button>
                  </template>
                  <template v-else>
                    <span v-if="row.archive?.status === 'failed'" class="text-small text-ink-2">{{ $t('settings.exportFailed') }}</span>
                    <Button variant="outline" @click="create(row.gallery)">{{ $t('settings.exportCreate') }}</Button>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
