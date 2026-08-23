<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'

defineProps<{ galleries: App.Gallery.Data.GalleryRowData[] }>()

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'
}
</script>

<template>
  <AdminLayout active="galerien">
    <div data-tokens>
      <PageHead :title="$t('galleries.title')">
        <Button :as="Link" variant="pill" class="ml-auto" href="/galerien/neu">{{ $t('galleries.create') }}</Button>
      </PageHead>

      <div class="max-w-[900px] px-8 pt-7 pb-8">
        <div
          v-for="gallery in galleries"
          :key="gallery.id"
          class="flex items-baseline gap-3.5 py-3 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
        >
          <span class="min-w-0 flex-1 text-[13.5px] leading-[1.47]">
            <Link :href="`/galerien/${gallery.id}`">{{ gallery.title ?? '—' }}</Link>
            <span v-if="gallery.projectName" class="text-[12px] text-ink-2"> · {{ gallery.projectName }}</span>
          </span>
          <span class="w-14 flex-none text-right text-[12.5px] tabular-nums text-ink-2">{{ gallery.assetCount }}</span>
          <span class="w-20 flex-none text-right text-[12.5px] tabular-nums text-ink-2">
            {{ gallery.version ? $t('galleryRecord.version', { version: gallery.version }) : '—' }}
          </span>
          <span class="w-20 flex-none text-right text-[12.5px] tabular-nums text-ink-2">{{ day(gallery.expiresAt) }}</span>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
