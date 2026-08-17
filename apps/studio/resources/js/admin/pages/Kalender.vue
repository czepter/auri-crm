<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

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
    <div data-theme="crm">
      <div class="hrow">
        <div>
          <div class="kick">{{ label }}</div>
          <h1>{{ $t('calendar.title') }}</h1>
        </div>

        <span class="seg" style="margin-left: auto">
          <button type="button" :class="{ on: view === 'monat' }" @click="view = 'monat'">
            {{ $t('calendar.viewMonth') }}
          </button>
          <button type="button" :class="{ on: view === 'liste' }" @click="view = 'liste'">
            {{ $t('calendar.viewList') }}
          </button>
        </span>

        <button class="btn-2" type="button" @click="go(previous)">←</button>
        <button class="btn-2" type="button" @click="go(next)">→</button>
      </div>

      <div v-if="view === 'monat'" class="cal" style="padding: var(--s5) var(--s7)">
        <div v-for="weekday in WEEKDAYS" :key="weekday" class="dow">{{ weekday }}</div>

        <div
          v-for="day in days"
          :key="day.date"
          class="day"
          :class="{ pale: !day.inMonth, today: day.today }"
        >
          <span class="n">{{ dayNumber(day.date) }}</span>

          <component
            :is="entry.projectId ? Link : 'span'"
            v-for="(entry, index) in entries(day)"
            :key="index"
            class="e"
            :class="entry.kind"
            :href="entry.projectId ? `/auftraege/${entry.projectId}` : undefined"
          >
            <span v-if="entry.time" class="tm">{{ entry.time }} </span>
            {{ entry.what }}
            <span v-if="entry.who" class="who"> · {{ entry.who }}</span>
          </component>

          <span v-if="overflow(day)" class="e away">{{ $t('calendar.more', { count: overflow(day) }) }}</span>
        </div>
      </div>

      <table v-else style="margin-top: var(--s5)">
        <thead>
          <tr>
            <th>{{ $t('calendar.colDate') }}</th>
            <th>{{ $t('calendar.colWhat') }}</th>
            <th>{{ $t('calendar.colVenue') }}</th>
            <th>{{ $t('calendar.colCrew') }}</th>
            <th>{{ $t('calendar.colStatus') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="[key, rows] in groups" :key="key">
            <tr><td class="grp" colspan="5">{{ $t(`calendar.group.${key}`) }}</td></tr>
            <tr v-for="shoot in rows" :key="shoot.id">
              <td>{{ shortDate(shoot.date) }}<template v-if="shoot.callTime"> {{ shoot.callTime }}</template></td>
              <td class="nm">
                <Link :href="`/auftraege/${projects[shoot.id].id}`">{{ projects[shoot.id].name }}</Link>
              </td>
              <td class="mu">{{ shoot.venue ?? '—' }}</td>
              <td class="mu">{{ shoot.crew.map((member) => member.name).join(', ') || '—' }}</td>
              <td :class="{ w: mine.includes(shoot.id) }">{{ shoot.statusLabel }}</td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
