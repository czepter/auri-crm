<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { isoDate } from '@/admin/lib/dates'
import { addDays, seasonYears, weekMondays } from '@/admin/lib/season'

type Phase = 'upcoming' | 'editing' | 'delivered'

const props = defineProps<{
  season: {
    shoots: { date: string; name: string; projectId: string; status: string; phase: Phase }[]
    away: { from: string; to: string; what: string; who: string | null }[]
  }
}>()

const emit = defineEmits<{ range: [label: string] }>()

/*
 * #365: the whole season at a glance — one row of weeks per year, a year
 * opened out into its weekdays on demand. A free week stays an empty cell,
 * the way a free day does in the month.
 */
const today = isoDate(new Date())
const thisYear = Number(today.slice(0, 4))
const bounds = computed(() => seasonYears(props.season.shoots.map((shoot) => shoot.date), today))

const from = ref(thisYear)
const to = ref(thisYear + 1)
const open = ref(new Set<number>())

watchEffect(() => emit('range', from.value === to.value ? String(from.value) : `${from.value} – ${to.value}`))

const WEEKDAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
const MONTHS = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
const TONE: Record<Phase, string> = { upcoming: 'bg-foreground', editing: 'bg-due', delivered: 'bg-ok' }
const HATCH = 'repeating-linear-gradient(135deg, var(--mark) 0 2px, var(--bg) 2px 5px)'

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

function cell(start: string, end: string) {
  const shoots = props.season.shoots.filter((shoot) => shoot.date >= start && shoot.date <= end)
  const away = props.season.away.filter((entry) => entry.from <= end && entry.to >= start)

  return {
    shoots,
    away: away.length > 0,
    past: end < today,
    now: start <= today && today <= end,
    double: new Set(shoots.map((shoot) => shoot.date)).size < shoots.length,
    title: [
      ...shoots.map((shoot) => `${shoot.name} · ${day(shoot.date)} · ${shoot.status}`),
      ...away.map((entry) => [entry.what, entry.who, `${day(entry.from)}–${day(entry.to)}`].filter(Boolean).join(' · ')),
    ].join('\n'),
  }
}

const years = computed(() => Array.from({ length: to.value - from.value + 1 }, (_, index) => {
  const year = from.value + index
  const mondays = weekMondays(year)

  return {
    year,
    booked: props.season.shoots.filter((shoot) => shoot.date.startsWith(`${year}-`)).length,
    columns: mondays.length,
    months: MONTHS.map((label, month) => {
      const first = `${year}-${String(month + 1).padStart(2, '0')}-01`
      const at = Math.max(0, mondays.filter((monday) => monday <= first).length - 1)

      return { label, column: `${at + 2} / span ${Math.min(3, mondays.length - at)}` }
    }),
    weeks: mondays.map((monday) => ({
      monday,
      week: cell(monday, addDays(monday, 6)),
      days: WEEKDAYS.map((_, offset) => cell(addDays(monday, offset), addDays(monday, offset))),
    })),
  }
}))

function toggle(year: number) {
  const next = new Set(open.value)
  next.has(year) ? next.delete(year) : next.add(year)
  open.value = next
}

function classes(entry: ReturnType<typeof cell>, height: string) {
  return [
    height,
    'block rounded-[3px]',
    entry.shoots.length ? `${TONE[entry.shoots[0].phase]} flex justify-center` : entry.away ? '' : entry.past ? 'bg-sunk' : 'border border-rule',
    entry.away && !entry.shoots.length && entry.past && 'opacity-50',
    entry.double && !entry.now && 'shadow-[0_0_0_2px_var(--bg),0_0_0_3.5px_var(--late)]',
    entry.now && 'outline outline-[1.5px] outline-offset-1 outline-foreground',
  ]
}
</script>

<template>
  <div class="px-6 pt-6.5 pb-8">
    <Button class="mb-6" type="button" variant="pill-muted" :disabled="from <= bounds.first" @click="from--">
      <svg class="size-3.5 fill-none stroke-current stroke-[1.75] [stroke-linecap:round] [stroke-linejoin:round]" viewBox="0 0 24 24"><path d="m6 15 6-6 6 6" /></svg>
      {{ $t('calendar.earlier') }}
    </Button>

    <section v-for="row in years" :key="row.year" class="mb-9">
      <div class="mb-2.5 flex items-baseline gap-2.5">
        <b class="text-title font-semibold tracking-[-0.02em] tabular-nums">{{ row.year }}</b>
        <span class="text-small text-ink-2">{{ $t('calendar.booked', { count: row.booked }) }}</span>
        <button
          class="ml-auto flex cursor-pointer items-center gap-1 text-small text-ink-2 hover:text-foreground"
          type="button"
          :aria-expanded="open.has(row.year)"
          @click="toggle(row.year)"
        >
          <svg class="size-3.5 fill-none stroke-current stroke-[1.75] [stroke-linecap:round] [stroke-linejoin:round]" viewBox="0 0 24 24">
            <path :d="open.has(row.year) ? 'm6 15 6-6 6 6' : 'm6 9 6 6 6-6'" />
          </svg>
          {{ $t('calendar.weekdays') }}
        </button>
      </div>

      <div
        class="grid items-end gap-x-[3px]"
        :class="open.has(row.year) ? 'gap-y-[3px]' : 'gap-y-1.5'"
        :style="{ gridTemplateColumns: `24px repeat(${row.columns}, minmax(0, 1fr))` }"
      >
        <span
          v-for="month in row.months"
          :key="month.label"
          class="text-small leading-none font-semibold whitespace-nowrap text-ink-2"
          :style="{ gridRow: 1, gridColumn: month.column }"
        >{{ month.label }}</span>

        <template v-if="open.has(row.year)">
          <template v-for="(weekday, offset) in WEEKDAYS" :key="weekday">
            <span class="text-small leading-[14px] text-ink-2" :style="{ gridRow: offset + 2, gridColumn: 1 }">{{ weekday }}</span>
            <component
              :is="week.days[offset].shoots.length === 1 ? Link : 'span'"
              v-for="(week, column) in row.weeks"
              :key="week.monday"
              :class="classes(week.days[offset], 'h-3.5')"
              :style="{ gridRow: offset + 2, gridColumn: column + 2, background: week.days[offset].away && !week.days[offset].shoots.length ? HATCH : undefined }"
              :href="week.days[offset].shoots.length === 1 ? `/auftraege/${week.days[offset].shoots[0].projectId}` : undefined"
              :title="week.days[offset].title || undefined"
            />
          </template>
        </template>

        <template v-else>
          <component
            :is="week.week.shoots.length === 1 ? Link : 'span'"
            v-for="(week, column) in row.weeks"
            :key="week.monday"
            :class="classes(week.week, 'h-[18px]')"
            :style="{ gridRow: 2, gridColumn: column + 2, background: week.week.away && !week.week.shoots.length ? HATCH : undefined }"
            :href="week.week.shoots.length === 1 ? `/auftraege/${week.week.shoots[0].projectId}` : undefined"
            :title="week.week.title || undefined"
          >
            <b v-if="week.week.shoots.length > 1" class="text-small leading-[18px] font-semibold text-paper">{{ week.week.shoots.length }}</b>
          </component>
        </template>
      </div>
    </section>

    <Button class="mb-6" type="button" variant="pill-muted" :disabled="to >= bounds.last" @click="to++">
      <svg class="size-3.5 fill-none stroke-current stroke-[1.75] [stroke-linecap:round] [stroke-linejoin:round]" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg>
      {{ $t('calendar.later') }}
    </Button>

    <div class="flex flex-wrap gap-4.5 text-small text-ink-2">
      <span v-for="(tone, phase) in TONE" :key="phase" class="flex items-center gap-1.5"><i class="size-3 rounded-[3px]" :class="tone" />{{ $t(`projects.phase.${phase}`) }}</span>
      <span class="flex items-center gap-1.5"><i class="size-3 rounded-[3px] border border-rule" />{{ $t('calendar.free') }}</span>
      <span class="flex items-center gap-1.5"><i class="size-3 rounded-[3px]" :style="{ background: HATCH }" />{{ $t('calendar.away') }}</span>
      <span class="flex items-center gap-1.5"><i class="size-3 rounded-[3px] bg-foreground shadow-[0_0_0_1.5px_var(--bg),0_0_0_3px_var(--late)]" />{{ $t('calendar.double') }}</span>
      <span class="flex items-center gap-1.5"><i class="size-3 rounded-[3px] border border-rule outline outline-[1.5px] outline-offset-1 outline-foreground" />{{ $t('calendar.thisWeek') }}</span>
    </div>
  </div>
</template>
