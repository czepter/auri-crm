<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import PageHead from '@/ui/page/PageHead.vue'
import { Table, TableCell, TableGroupRow, TableHead, TableRow } from '@/ui/table'

const props = defineProps<{
  month: string
  label: string
  previous: string
  next: string
  days: App.Crm.Data.CalendarDayData[]
  shoots: App.Crm.Data.ShootData[]
  projects: Record<string, { id: string; name: string }>
  mine: string[]
}>()

const view = ref('monat')

// Three lines, then a count. The frame is six week rows and it has to stay
// six: a wedding with four shoots on one day would otherwise push the rest of
// the month down a screen. The draft has no such day; this is the ruling for
// when one turns up.
const SHOWN = 3

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

function go(month: string) {
  router.get('/kalender', { monat: month }, { preserveScroll: true, preserveState: true })
}

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

function shortDate(date: string | null) {
  return date ? new Date(date).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' }) : '—'
}
</script>

<template>
  <AdminLayout active="kalender">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="label" :title="$t('calendar.title')">
          <ChoiceGroup
            v-model="view"
            class="ml-auto"
            :options="[
              { value: 'monat', label: $t('calendar.viewMonth') },
              { value: 'liste', label: $t('calendar.viewList') },
            ]"
            variant="segmented"
          />

          <Button
            v-for="step in ([['←', previous], ['→', next]] as const)"
            :key="step[0]"
            type="button"
            variant="pill-muted"
            @click="go(step[1])"
          >
            {{ step[0] }}
          </Button>
        </PageHead>

        <div v-if="view === 'monat'" class="grid grid-cols-7 px-8 py-5">
          <div
            v-for="weekday in WEEKDAYS"
            :key="weekday"
            class="pb-2 pl-0.5 text-[10.5px] leading-none font-semibold tracking-[0.06em] uppercase text-ink-2"
          >
            {{ weekday }}
          </div>

          <div
            v-for="day in days"
            :key="day.date"
            class="min-h-[104px] px-2 pt-2 pb-2.5 shadow-[inset_0_1px_0_var(--rule),inset_1px_0_0_var(--rule)] [&:nth-child(7n+1)]:shadow-[inset_0_1px_0_var(--rule)]"
          >
            <span
              class="text-[12.5px] tabular-nums"
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
              class="mt-[5px] block text-[11.5px] no-underline"
              :class="{ hold: 'text-action', away: 'text-ink-2' }[entry.kind ?? ''] ?? 'text-inherit'"
              :href="entry.projectId ? `/auftraege/${entry.projectId}` : undefined"
            >
              <span
                v-if="entry.time"
                class="tabular-nums"
                :class="entry.kind === 'away' ? 'font-normal' : 'font-semibold'"
              >{{ entry.time }} </span>
              {{ entry.what }}
              <!-- The measured values, not the ones crm.css .cal .e .who alone would
                   suggest: a second .who rule reaches this span as well. -->
              <span
                v-if="entry.who"
                class="pr-[18px] text-[13.5px] leading-[1.35] font-semibold tracking-[-0.015em] text-ink-2"
              > · {{ entry.who }}</span>
            </component>

            <span v-if="overflow(day)" class="mt-[5px] block text-[11.5px] text-ink-2">
              {{ $t('calendar.more', { count: overflow(day) }) }}
            </span>
          </div>
        </div>

        <Table v-else>
          <thead>
            <tr>
              <TableHead>{{ $t('calendar.colDate') }}</TableHead>
              <TableHead>{{ $t('calendar.colWhat') }}</TableHead>
              <TableHead>{{ $t('calendar.colVenue') }}</TableHead>
              <TableHead>{{ $t('calendar.colCrew') }}</TableHead>
              <TableHead>{{ $t('calendar.colStatus') }}</TableHead>
            </tr>
          </thead>
          <tbody>
            <template v-for="[key, rows] in groups" :key="key">
              <TableGroupRow :colspan="5">
                  {{ $t(`calendar.group.${key}`) }}
              </TableGroupRow>
              <TableRow v-for="shoot in rows" :key="shoot.id">
                <TableCell>{{ shortDate(shoot.date) }}<template v-if="shoot.callTime"> {{ shoot.callTime }}</template></TableCell>
                <TableCell tone="name">
                  <Link :href="`/auftraege/${projects[shoot.id].id}`">{{ projects[shoot.id].name }}</Link>
                </TableCell>
                <TableCell tone="muted">{{ shoot.venue ?? '—' }}</TableCell>
                <TableCell tone="muted">{{ shoot.crew.map((member) => member.name).join(', ') || '—' }}</TableCell>
                <TableCell :class="mine.includes(shoot.id) && 'text-action'">{{ shoot.statusLabel }}</TableCell>
              </TableRow>
            </template>
          </tbody>
        </Table>
      </div>
    </Page>
  </AdminLayout>
</template>
