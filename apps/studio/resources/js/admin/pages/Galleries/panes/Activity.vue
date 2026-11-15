<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Link, router } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { ChoiceGroup } from '@/ui/choice-group'
import { FILTERS, filterEvents, groupByDay, type ActivityEvent, type ActivityFilter, type Photo } from '@/admin/lib/galleryActivity'

export interface ActivityData {
  counts: { favorites: number; comments: number; hidden: number; downloads: number }
  events: ActivityEvent[]
  favorites: Photo[]
  hidden: { id: string; who: string; photo: Photo }[]
  task: { available: boolean; url: string | null }
}

const props = defineProps<{
  galleryId: string
  activity: ActivityData
  builds: { id: string; size: string; status: string; parts: number; bytes: number }[]
}>()

const { t } = useI18n()

const filter = ref<ActivityFilter>('all')
const days = computed(() => groupByDay(filterEvents(props.activity.events, filter.value)))
const options = computed(() => FILTERS.map((value) => ({
  value,
  label: value === 'all'
    ? t('galleries.filterAll')
    : `${t(`galleryRecord.activity.${value}`)} (${props.activity.counts[value]})`,
})))

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function megabytes(bytes: number) {
  return `${Math.round(bytes / 1_000_000)} MB`
}

function restore(id: string) {
  router.delete(`/galerien/${props.galleryId}/ausgeblendet/${id}`, { preserveScroll: true })
}

const replying = ref<string | null>(null)
const draft = ref('')

function reply(id: string) {
  router.post(`/galerien/${props.galleryId}/kommentare/${id}/antwort`, { body: draft.value }, {
    preserveScroll: true,
    onSuccess: () => {
      replying.value = null
      draft.value = ''
    },
  })
}

function createTask() {
  router.post(`/galerien/${props.galleryId}/favoriten-aufgabe`, {}, { preserveScroll: true })
}
</script>

<template>
  <div class="flex min-w-0 flex-1 flex-col gap-8 min-[1280px]:flex-row">
    <div class="min-w-0 flex-1">
      <ChoiceGroup v-model="filter" :options="options" />

      <section v-for="group in days" :key="group.day" class="mt-5">
        <h3 class="m-0 mb-1 text-small font-semibold text-ink-2">{{ day(group.day) }}</h3>
        <ul class="m-0 list-none p-0">
          <li
            v-for="(event, index) in group.events"
            :key="index"
            class="flex items-start gap-3 py-3 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
          >
            <template v-if="event.type === 'comment'">
              <img :src="event.photo.src" alt="" class="size-12 flex-none rounded-[var(--r-sm)] bg-sunk object-cover" loading="lazy">
              <div class="min-w-0 flex-1">
                <div class="text-ui font-semibold">{{ $t(event.studio ? 'galleryRecord.activity.replied' : 'galleryRecord.activity.commented', { who: event.who ?? $t('galleryRecord.guest'), n: event.photo.n }) }}</div>
                <p class="m-0 mt-0.5 text-small break-words text-ink-2">{{ event.text }}</p>
                <form v-if="replying === event.id" class="mt-2 flex gap-2" @submit.prevent="reply(event.id)">
                  <Input v-model="draft" required maxlength="2000" :aria-label="$t('galleryRecord.activity.reply')" />
                  <Button class="ml-auto" type="submit" :disabled="!draft.trim()">{{ $t('galleryRecord.activity.send') }}</Button>
                </form>
              </div>
              <Button v-if="!event.studio && replying !== event.id" type="button" variant="outline" @click="replying = event.id; draft = ''">{{ $t('galleryRecord.activity.reply') }}</Button>
            </template>

            <template v-else-if="event.type === 'favorites'">
              <div class="min-w-0 flex-1">
                <div class="text-ui font-semibold">{{ $t('galleryRecord.activity.marked', { who: event.who ?? $t('galleryRecord.guest') }, event.photos.length) }}</div>
                <ul class="m-0 mt-2 flex list-none flex-wrap gap-2 p-0">
                  <li v-for="photo in event.photos" :key="photo.n" class="text-center text-small text-ink-2 tabular-nums">
                    <img :src="photo.src" alt="" class="size-10 rounded-[var(--r-sm)] bg-sunk object-cover" loading="lazy">
                    {{ photo.n }}
                  </li>
                </ul>
              </div>
            </template>

            <template v-else-if="event.type === 'hide'">
              <img :src="event.photo.src" alt="" class="size-12 flex-none rounded-[var(--r-sm)] bg-sunk object-cover" loading="lazy">
              <div class="min-w-0 flex-1 text-ui font-semibold">{{ $t('galleryRecord.activity.hid', { who: event.who, n: event.photo.n }) }}</div>
              <Button type="button" variant="outline" @click="restore(event.id)">{{ $t('galleryRecord.activity.restore') }}</Button>
            </template>

            <template v-else-if="event.type === 'download'">
              <div class="min-w-0 flex-1">
                <div class="text-ui font-semibold">{{ $t('galleryRecord.activity.downloaded') }}</div>
                <p class="m-0 mt-0.5 text-small text-ink-2">{{ event.what ?? $t('gallery.everything') }} · {{ $t(`gallery.size.${event.size}`) }}</p>
              </div>
            </template>

            <template v-else>
              <div class="min-w-0 flex-1 text-ui font-semibold">{{ $t('galleryRecord.activity.opened') }}</div>
              <span class="text-small text-ink-2 tabular-nums">{{ $t('galleryRecord.activity.views', event.count) }}</span>
            </template>
          </li>
        </ul>
      </section>

      <h2 class="mt-9">{{ $t('galleryRecord.downloadsHeading') }}</h2>
      <table v-if="builds.length" class="w-full border-collapse max-[640px]:block max-[640px]:overflow-x-auto">
        <thead>
          <tr>
            <th scope="col" class="px-6 pb-2 text-left text-small leading-none font-semibold text-ink-2">{{ $t('gallery.sizeLabel') }}</th>
            <th scope="col" class="px-6 pb-2 text-left text-small leading-none font-semibold text-ink-2">{{ $t('galleryRecord.parts') }}</th>
            <th scope="col" class="px-6 pb-2 text-right text-small leading-none font-semibold text-ink-2">{{ $t('galleryRecord.size') }}</th>
            <th scope="col" class="px-6 pb-2 text-left text-small leading-none font-semibold text-ink-2">{{ $t('galleryRecord.state') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="build in builds" :key="build.id">
            <td class="px-6 py-2.5 align-baseline text-ui font-semibold tracking-[-0.014em] shadow-[inset_0_1px_0_var(--rule)]">{{ $t(`gallery.size.${build.size}`) }}</td>
            <td class="px-6 py-2.5 align-baseline text-small text-ink-2 shadow-[inset_0_1px_0_var(--rule)]">{{ build.parts }}</td>
            <td class="px-6 py-2.5 text-right align-baseline text-small tabular-nums shadow-[inset_0_1px_0_var(--rule)]">{{ megabytes(build.bytes) }}</td>
            <td class="px-6 py-2.5 align-baseline text-small text-ink-2 shadow-[inset_0_1px_0_var(--rule)]">{{ build.status }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else class="text-small leading-[1.45] text-faint">{{ $t('galleryRecord.noBuilds') }}</p>
    </div>

    <aside v-if="activity.favorites.length || activity.hidden.length" class="flex flex-none flex-col gap-6 min-[1280px]:w-[280px]">
      <section v-if="activity.favorites.length">
        <h3 class="m-0 mb-2 text-ui font-semibold">{{ $t('galleryRecord.activity.favorites') }}</h3>
        <ul class="m-0 flex list-none flex-wrap gap-2 p-0">
          <li v-for="photo in activity.favorites" :key="photo.n" class="text-center text-small text-ink-2 tabular-nums">
            <img :src="photo.src" alt="" class="size-12 rounded-[var(--r-sm)] bg-sunk object-cover" loading="lazy">
            {{ photo.n }}
          </li>
        </ul>
        <div class="mt-3 flex flex-wrap gap-2">
          <Button v-if="activity.task.url" :as="Link" variant="outline" :href="activity.task.url">{{ $t('galleryRecord.activity.openTask') }}</Button>
          <Button v-else-if="activity.task.available" type="button" variant="outline" @click="createTask">{{ $t('galleryRecord.activity.createTask') }}</Button>
          <Button :as="Link" variant="outline" :href="`/galerien/${galleryId}/bilder?favoriten=1`">{{ $t('galleryRecord.activity.onlyFavorites') }}</Button>
        </div>
      </section>

      <section v-if="activity.hidden.length">
        <h3 class="m-0 mb-2 text-ui font-semibold">{{ $t('galleryRecord.activity.hidden') }}</h3>
        <ul class="m-0 list-none p-0">
          <li v-for="row in activity.hidden" :key="row.id" class="flex items-center gap-3 py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none">
            <img :src="row.photo.src" alt="" class="size-10 flex-none rounded-[var(--r-sm)] bg-sunk object-cover" loading="lazy">
            <span class="min-w-0 flex-1 text-small">{{ $t('galleryRecord.activity.photo', { n: row.photo.n }) }}</span>
            <Button type="button" variant="ghost" @click="restore(row.id)">{{ $t('galleryRecord.activity.restore') }}</Button>
          </li>
        </ul>
      </section>
    </aside>
  </div>
</template>
