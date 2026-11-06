<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/crm/ProjectShell.vue'
import Card from '@/ui/page/Card.vue'
import { Button } from '@/ui/button'
import { dayWithWeekday as day } from '@/admin/lib/dates'
import { dayAndMonth, weekday } from '@/admin/lib/shoots'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  header: App.Crm.Screens.CaseHeaderData
  galleries: App.Crm.Screens.CaseGalleryData[]
  shoots: App.Crm.Data.ShootData[]
}>()
useLiveProps(['header', 'galleries', 'shoots'])

/* tokens.css .chip's four tones. */
const TONE: Record<string, string> = {
  draft: 'bg-muted text-muted-foreground',
  prerelease: 'bg-due-bg text-due',
  published: 'bg-ok-bg text-ok',
  withdrawn: 'bg-muted text-faint',
}

// Eine Galerie ohne Shooting gehört dem ganzen Auftrag und deckt jedes Shooting ab.
const withoutGallery = computed(() => {
  if (props.galleries.some((gallery) => gallery.shootId === null)) return []

  const taken = new Set(props.galleries.map((gallery) => gallery.shootId))

  return props.shoots.filter((shoot) => !shoot.isCancelled && !taken.has(shoot.id))
})

const gallerySummary = computed(() => props.galleries.map((gallery) => gallery.title ?? '—').join(', '))

const missingSummary = computed(() =>
  withoutGallery.value.map((shoot) => shoot.label ?? shoot.kind).join(', ')
)

</script>

<template>
  <AdminLayout :active="header.isBooked ? 'projekte' : 'anfragen'">
    <ProjectShell :header="header" active="galleries">
      <template #actions>
        <Button :as="Link" variant="pill" :href="`/galerien/neu?projekt=${header.id}`">{{ $t('galleries.create') }}</Button>
      </template>

      <div class="flex flex-col gap-3">
        <Card :title="$t('galleries.title')" :summary="gallerySummary">
          <div
            v-for="gallery in galleries"
            :key="gallery.id"
            class="flex items-baseline gap-2.5 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
          >
            <div class="min-w-0 flex-1">
              <Link :href="`/galerien/${gallery.id}`" class="text-small leading-[1.3] font-semibold tracking-[-0.01em]">
                {{ gallery.title ?? '—' }}
              </Link>
              <span class="mt-0.5 block text-small leading-[1.35] text-muted-foreground">
                {{
                  [
                    $t('project.galleryImages', gallery.assetCount),
                    gallery.version ? $t('galleryRecord.version', { version: gallery.version }) : null,
                    gallery.expiresAt ? $t('project.galleryExpires', { date: day(gallery.expiresAt) }) : $t('project.galleryNoExpiry'),
                    gallery.inPortal ? $t('project.galleryInPortal') : $t('project.galleryNotInPortal'),
                  ].filter(Boolean).join(' · ')
                }}
              </span>
            </div>
            <span :class="['flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-small leading-[1.5] font-semibold', TONE[gallery.state]]">
              {{ $t(`project.galleryState.${gallery.state}`) }}
            </span>
          </div>
        </Card>

        <!-- Der Platz für das Shooting, das noch keine Galerie hat: kein leerer
             Text, sondern der Knopf, der sie anlegt. -->
        <Card v-if="withoutGallery.length" :title="$t('project.galleriesMissing')" :summary="missingSummary">
          <div
            v-for="shoot in withoutGallery"
            :key="shoot.id"
            class="flex items-center gap-2.5 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
          >
            <div class="w-[88px] flex-none text-small leading-[1.2] font-semibold tabular-nums">
              {{ dayAndMonth(shoot.date) ?? '—' }}
              <em class="mt-0.5 block text-small leading-[1.3] font-normal not-italic text-faint">
                {{ weekday(shoot.date) ?? $t('board.openDate') }}
              </em>
            </div>
            <span class="min-w-0 flex-1 text-small leading-[1.3] font-semibold tracking-[-0.01em]">{{ shoot.label ?? shoot.kind }}</span>
            <Button :as="Link" variant="outline" :href="`/galerien/neu?projekt=${header.id}&shooting=${shoot.id}`">{{ $t('galleries.create') }}</Button>
          </div>
        </Card>
      </div>
    </ProjectShell>
  </AdminLayout>
</template>
