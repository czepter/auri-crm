<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import CalendarSeason from '@/admin/components/crm/CalendarSeason.vue'
import Page from '@/ui/page/Page.vue'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import { QueueGroup, QueueItem } from '@/ui/queue'
import { Table, TableCell, TableGroupRow, TableHead, TableRow } from '@/ui/table'
import { useLiveProps } from '@/admin/lib/liveReload'
import { dayWithWeekday } from '@/admin/lib/dates'

const props = defineProps<{
  view: 'monat' | 'liste' | 'saison'
  month: string
  label: string
  previous: string
  next: string
  days: App.Crm.Data.CalendarDayData[]
  shoots: App.Crm.Data.ShootData[]
  projects: Record<string, { id: string; name: string }>
  mine: string[]
  season: InstanceType<typeof CalendarSeason>['$props']['season'] | null
  workingDays: number[] | null
}>()
useLiveProps(['days', 'shoots', 'projects', 'mine', 'season', 'workingDays'])

const seasonLabel = ref('')

// Three lines, then a count. The frame is six week rows and it has to stay
// six: a wedding with four shoots on one day would otherwise push the rest of
// the month down a screen. The draft has no such day; this is the ruling for
// when one turns up.
const SHOWN = 3
const chipKinds = ['task', 'quote', 'gallery']

function entryLabel(entry: App.Crm.Data.CalendarDayData['entries'][number]) {
  return [entry.time, entry.what, entry.who].filter(Boolean).join(' · ')
}

function entries(day: App.Crm.Data.CalendarDayData) {
  return day.entries.slice(0, SHOWN)
}

function overflow(day: App.Crm.Data.CalendarDayData) {
  return Math.max(0, day.entries.length - SHOWN)
}

const WEEKDAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']

function dayNumber(date: string) {
  return new Date(date).getDate()
}

function go(month: string, view: string = props.view) {
  router.get('/kalender', { monat: month, ansicht: view }, { preserveScroll: true, preserveState: true })
}

// A phone opens on the list unless the URL names a view.
onMounted(() => {
  if (window.matchMedia('(max-width: 640px)').matches && !new URLSearchParams(location.search).has('ansicht')) {
    router.get('/kalender', { monat: props.month, ansicht: 'liste' }, { replace: true, preserveState: true })
  }
})

// The list answers "what is next", so it groups by nearness rather than by
// the month the grid is showing.
const groups = computed(() => {
  const endOfWeek = new Date()
  endOfWeek.setDate(endOfWeek.getDate() + (7 - endOfWeek.getDay()))
  const endOfNext = new Date(endOfWeek)
  endOfNext.setDate(endOfNext.getDate() + 7)

  const buckets: Record<string, App.Crm.Data.ShootData[]> = { diese: [], naechste: [], spaeter: [] }

  for (const shoot of props.shoots) {
    if (!shoot.date) continue

    const date = new Date(shoot.date)
    const key = date <= endOfWeek ? 'diese' : date <= endOfNext ? 'naechste' : 'spaeter'
    buckets[key].push(shoot)
  }

  return Object.entries(buckets).filter(([, rows]) => rows.length)
})

</script>

<template>
  <AdminLayout active="kalender">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('calendar.title')" :title="view === 'saison' ? seasonLabel : label">
          <ChoiceGroup
            :model-value="view"
            class="ml-auto min-[641px]:order-2 max-[640px]:ml-0 max-[640px]:[&_span]:inline-flex max-[640px]:[&_span]:min-h-11 max-[640px]:[&_span]:items-center"
            :options="[
              { value: 'monat', label: $t('calendar.viewMonth') },
              { value: 'liste', label: $t('calendar.viewList') },
              { value: 'saison', label: $t('calendar.viewSeason') },
            ]"
            @update:model-value="(next) => go(month, next)"
          />

          <Button
            v-if="workingDays"
            :as="Link"
            variant="secondary"
            class="max-[640px]:hidden min-[641px]:order-3"
            :href="'/profil/kalender'"
          >
            {{ $t('stages.entry') }}
          </Button>

          <Button
            v-for="step in ([['←', previous], ['→', next]] as const)"
            v-show="view !== 'saison'"
            :key="step[0]"
            type="button"
            variant="secondary"
            class="min-[641px]:order-1 max-[640px]:min-h-11 max-[640px]:min-w-11"
            :class="step[0] === '←' && 'max-[640px]:ml-auto'"
            :aria-label="step[0] === '←' ? $t('calendar.previousMonth') : $t('calendar.nextMonth')"
            :title="step[0] === '←' ? $t('calendar.previousMonth') : $t('calendar.nextMonth')"
            @click="go(step[1])"
          >
            {{ step[0] }}
          </Button>

          <Button
            v-show="view !== 'saison'"
            :as="Link"
            variant="secondary"
            class="min-[641px]:order-1 max-[640px]:min-h-11"
            :href="`/kalender?ansicht=${view}`"
          >
            {{ $t('calendar.today') }}
          </Button>
        </PageHead>

        <div v-if="view === 'monat'" class="grid grid-cols-7 px-6 py-5 max-[640px]:px-2">
          <div
            v-for="weekday in WEEKDAYS"
            :key="weekday"
            class="pb-2 pl-0.5 text-small leading-none font-semibold text-ink-2"
          >
            {{ weekday }}
          </div>

          <div
            v-for="day in days"
            :key="day.date"
            class="min-h-[104px] overflow-hidden px-2 pt-2 pb-2.5 max-[640px]:px-1 border-t border-l border-rule [&:nth-child(7n+1)]:border-l-0"
          >
            <span
              class="text-small tabular-nums"
              :class="day.today
                ? 'inline-block min-w-5 rounded-full bg-foreground px-[5px] py-px text-center font-semibold text-paper'
                : day.inMonth ? 'font-semibold' : 'font-normal text-ink-2'"
            >
              {{ dayNumber(day.date) }}
            </span>

            <component
              :is="entry.projectId ? Link : 'span'"
              v-for="(entry, index) in entries(day)"
              :key="index"
              class="mt-[5px] block text-small no-underline max-[640px]:mt-0 max-[640px]:inline-block max-[640px]:min-w-4 max-[640px]:py-2 max-[640px]:text-center"
              :aria-label="entryLabel(entry)"
              :class="{ hold: 'text-ring', away: 'text-ink-2' }[entry.kind ?? ''] ?? 'text-inherit'"
              :href="entry.projectId ? `/auftraege/${entry.projectId}` : undefined"
            >
              <span
                v-if="entry.time"
                class="mr-1 tabular-nums max-[640px]:hidden"
                :class="entry.kind === 'away' ? 'font-normal' : 'font-semibold'"
              >{{ entry.time }}</span>
              <!-- A phone cell is 50px wide: one dot per entry, still a link to its project. -->
              <span class="hidden max-[640px]:inline">•</span>
              <span
                v-if="chipKinds.includes(entry.kind)"
                class="mr-1 rounded-sm bg-rule px-1 text-small font-semibold text-ink-2 max-[640px]:hidden"
              >{{ $t(`calendar.kind.${entry.kind}`) }}</span>
              <span class="max-[640px]:hidden">{{ entry.what }}</span>
              <!-- The measured values, not the ones crm.css .cal .e .who alone would
                   suggest: a second .who rule reaches this span as well. -->
              <span
                v-if="entry.who"
                class="pr-[18px] text-ui leading-[1.35] font-semibold tracking-[-0.015em] text-ink-2 max-[640px]:hidden"
              > · {{ entry.who }}</span>
            </component>

            <span v-if="overflow(day)" class="mt-[5px] block text-small text-ink-2">
              {{ $t('calendar.more', { count: overflow(day) }) }}
            </span>
          </div>
        </div>

        <template v-else-if="view === 'saison'">
          <CalendarSeason v-if="season" :season="season" @range="seasonLabel = $event" />
        </template>

        <div v-if="view !== 'saison'" class="hidden px-3 pt-2 max-[640px]:block">
          <Empty v-if="!groups.length" :text="$t('dashboard.weekEmpty')">
            <div class="flex items-center gap-2">
              <Button :as="Link" variant="default" href="/anfragen/neu">{{ $t('leads.createKicker') }}</Button>
              <Button :as="Link" variant="secondary" href="/projekte">{{ $t('calendar.toProjects') }}</Button>
            </div>
          </Empty>

          <template v-for="[key, rows] in groups" :key="key">
            <QueueGroup :title="$t(`calendar.group.${key}`)" :count="rows.length" />
            <QueueItem
              v-for="shoot in rows"
              :key="shoot.id"
              class="min-h-14"
              :href="`/auftraege/${projects[shoot.id].id}`"
              :name="projects[shoot.id].name"
              :meta="shoot.statusLabel"
              :title="[dayWithWeekday(shoot.date) + (shoot.callTime ? ` ${shoot.callTime}` : ''), shoot.venue].filter(Boolean).join(' · ')"
            />
          </template>
        </div>

        <Table v-if="view === 'liste'" class="max-[640px]:hidden">
          <thead>
            <tr>
              <TableHead>{{ $t('calendar.colDate') }}</TableHead>
              <TableHead>{{ $t('calendar.colWhat') }}</TableHead>
              <TableHead>{{ $t('calendar.colVenue') }}</TableHead>
              <TableHead v-if="!$page.props.soloStudio">{{ $t('calendar.colCrew') }}</TableHead>
              <TableHead>{{ $t('calendar.colStatus') }}</TableHead>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!groups.length">
              <TableCell :colspan="5">
                <Empty :text="$t('dashboard.weekEmpty')">
                  <div class="flex items-center gap-2">
                    <Button :as="Link" variant="default" href="/anfragen/neu">{{ $t('leads.createKicker') }}</Button>
                    <Button :as="Link" variant="secondary" href="/projekte">{{ $t('calendar.toProjects') }}</Button>
                  </div>
                </Empty>
              </TableCell>
            </tr>

            <template v-for="[key, rows] in groups" :key="key">
              <TableGroupRow :colspan="5">
                  {{ $t(`calendar.group.${key}`) }}
              </TableGroupRow>
              <TableRow v-for="shoot in rows" :key="shoot.id">
                <TableCell>{{ dayWithWeekday(shoot.date) }}<template v-if="shoot.callTime"> {{ shoot.callTime }}</template></TableCell>
                <TableCell tone="name">
                  <Link :href="`/auftraege/${projects[shoot.id].id}`">{{ projects[shoot.id].name }}</Link>
                </TableCell>
                <TableCell tone="muted">{{ shoot.venue ?? '—' }}</TableCell>
                <TableCell v-if="!$page.props.soloStudio" tone="muted">{{ shoot.crew.map((member) => member.name).join(', ') || '—' }}</TableCell>
                <TableCell :class="mine.includes(shoot.id) && 'text-ring'">{{ shoot.statusLabel }}</TableCell>
              </TableRow>
            </template>
          </tbody>
        </Table>

        <Button
          v-if="workingDays"
          :as="Link"
          variant="secondary"
          class="mx-4 mt-4 hidden min-h-11 max-[640px]:inline-flex"
          :href="'/profil/kalender'"
        >
          {{ $t('stages.entry') }}
        </Button>

      </div>
    </Page>
  </AdminLayout>
</template>
