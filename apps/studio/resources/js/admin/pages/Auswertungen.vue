<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import ReportCard from '@/admin/components/crm/ReportCard.vue'
import ReportKpi from '@/admin/components/crm/ReportKpi.vue'
import { Button } from '@/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { ChoiceGroup } from '@/ui/choice-group'
import { formatEurosRounded } from '@/lib/money'
import { barHeight, ratePoints as ratePointsFor } from '@/admin/lib/chart'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  window: { from: string; to: string }
  filters: { period: string; type: string | null }
  periods: { value: string; label: string }[]
  projectTypes: { value: string; label: string }[]
  funnelByMonth: { month: string; captured: number; booked: number; rate: number | null }[]
  sources: { source: string; label: string; captured: number; booked: number; revenue: number }[]
  referrers: { name: string; count: number }[]
  lostReasons: { outcome: string; outcomeLabel: string; reason: string; count: number }[]
  acceptanceRate: number | null
  acceptanceRatePrior: number | null
  revenue: { projects: number; revenue: number; averagePerProject: number | null }
  revenuePrior: { projects: number; revenue: number; averagePerProject: number | null }
  perContact: { name: string; projects: number; revenue: number }[]
  cashFlow: { overdue: number; projects: number; quotes: number; months: { month: string; due: number; received: number; open: number }[] }
  cashFlowPast: { projects: number; months: { month: string; received: number }[] }
  revenueByType: { type: string; label: string; projects: number; revenue: number; average: number }[]
  steps: { key: string; label: string; leads: number; lost: number; drop: number | null }[]
  dwell: { key: string; label: string; days: number; moves: number }[]
  responseTimes: { buckets: { key: string; label: string; leads: number }[]; medianHours: number | null; unanswered: number }
  shootsByMonth: { month: string; shoots: number }[]
  leadTimes: { buckets: { key: string; label: string; projects: number }[]; median: string | null; withoutDate: number }
}>()
useLiveProps(['funnelByMonth', 'sources', 'referrers', 'lostReasons', 'acceptanceRate', 'acceptanceRatePrior', 'revenue', 'revenuePrior', 'perContact', 'revenueByType', 'cashFlow', 'cashFlowPast', 'steps', 'dwell', 'responseTimes', 'shootsByMonth', 'leadTimes'])

// Zeitraum und Typ stehen in der URL: eine gefilterte Auswertung ist ein Link.
function filter(next: { zeitraum?: string; typ?: string | null }) {
  router.get(
    '/auswertungen',
    {
      zeitraum: next.zeitraum ?? props.filters.period,
      ...(next.typ === undefined ? (props.filters.type ? { typ: props.filters.type } : {}) : next.typ ? { typ: next.typ } : {}),
    },
    { preserveScroll: true, preserveState: true, replace: true },
  )
}

const capturedTotal = computed(() => props.funnelByMonth.reduce((sum, m) => sum + m.captured, 0))

const bare = computed(() =>
  !capturedTotal.value
  && !props.sources.length
  && !props.referrers.length
  && !props.lostReasons.length
  && !props.perContact.length
  && !props.cashFlow.months.length
  && props.acceptanceRate === null
  && !props.revenue.projects,
)

// Minor units end to end, turned into euros exactly once — the same rule
// Uebersicht.vue states for its own tiles.

function monthShort(month: string) {
  const [year, index] = month.split('-')
  return new Date(Number(year), Number(index) - 1, 1).toLocaleDateString('de-DE', { month: 'short' })
}

// Neben den Monatsnamen ist auf einer Achse von 220px kein Platz für zwölf
// Jahreszahlen, unter ihnen schon. Sie steht dort, wo sie sich ändert: am
// ersten Monat und im Januar.
function yearTick(month: string, index: number) {
  const [year, ordinal] = month.split('-')

  return index === 0 || ordinal === '01' ? year.slice(2) : ''
}

// Unter 640px passt nicht jedes Monatslabel neben das nächste: erster Monat,
// dann Jan, Apr, Jul, Okt.
function monthShown(month: string, index: number) {
  return index === 0 || (Number(month.split('-')[1]) - 1) % 3 === 0
}

function monthLong(month: string) {
  const [year, index] = month.split('-')
  return new Date(Number(year), Number(index) - 1, 1).toLocaleDateString('de-DE', { month: 'short', year: 'numeric' })
}

// Trägt eine Reihe in weniger als drei Monaten einen Wert, sind zwölf
// Balken Leere mit zwei Ausreißern: dann die Monate als Liste.
const sparse = (values: number[]) => values.filter(Boolean).length < 3

function initials(name: string) {
  return name
    .replace(/[^\p{L} ]/gu, ' ')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
}

// A Vorjahresvergleich is only meaningful when there is a prior year to
// compare against. Null propagates rather than turning into "+100 %", which
// is what a studio's first year would otherwise read as.
function delta(now: number | null, prior: number | null) {
  if (now === null || prior === null || prior === 0) return null
  return Math.round(((now - prior) / prior) * 100)
}

function signed(value: number) {
  return value > 0 ? `+${value}` : `${value}`
}

function tone(value: number) {
  return value > 0 ? 'up' : value < 0 ? 'down' : 'flat'
}

const revenueDelta = computed(() => delta(props.revenue.revenue, props.revenuePrior.revenue))
const acceptanceDelta = computed(() =>
  props.acceptanceRate === null || props.acceptanceRatePrior === null
    ? null
    : props.acceptanceRate - props.acceptanceRatePrior,
)

// The tallest bar in the funnel sets the scale for all of them. An empty
// window leaves it at 1 so the division below never divides by zero.
const funnelPeak = computed(() => Math.max(1, ...props.funnelByMonth.map((m) => m.captured)))
// Erwartet und bisher sind dieselbe Frage in zwei Richtungen, also dieselbe
// Karte mit einem Schalter statt zweier Karten nebeneinander.
const cashMode = ref('expected')
const cashAhead = computed(() => cashMode.value === 'expected')

const cashMonths = computed(() =>
  cashAhead.value
    ? props.cashFlow.months.map((m) => ({ month: m.month, value: m.due, forecast: m.open }))
    : props.cashFlowPast.months.map((m) => ({ month: m.month, value: m.received, forecast: 0 })),
)

const funnelSparse = computed(() => sparse(props.funnelByMonth.map((m) => m.captured)))
const cashRows = computed(() => cashMonths.value.filter((m) => m.value || m.forecast))
const cashSparse = computed(() => sparse(cashMonths.value.map((m) => m.value + m.forecast)))
const shootsSparse = computed(() => sparse(props.shootsByMonth.map((m) => m.shoots)))
const hasSteps = computed(() => props.steps.slice(1).some((s) => s.leads > 0))

const cashPeak = computed(() =>
  Math.max(1, ...cashMonths.value.map((m) => Math.max(m.value, m.forecast))),
)

const bookedTotal = computed(() => props.funnelByMonth.reduce((sum, m) => sum + m.booked, 0))
const bookingRate = computed(() =>
  capturedTotal.value === 0 ? null : Math.round((bookedTotal.value / capturedTotal.value) * 100),
)

// Die Quotenlinie liegt als ein Pfad ueber den Balken, in einem viewBox mit
// fester Breite: das Diagramm ist fluide, die Punkte sind es nicht.
// non-scaling-stroke haelt die Linie dabei 2px breit.
const rateScale = computed(() => Math.max(50, ...props.funnelByMonth.map((m) => m.rate ?? 0)))
const ratePoints = computed(() =>
  ratePointsFor(props.funnelByMonth.map((m) => m.rate), rateScale.value),
)

const sourcePeak = computed(() => Math.max(1, ...props.sources.map((s) => s.revenue)))
const lostPeak = computed(() => Math.max(1, ...props.lostReasons.map((r) => r.count)))
const lostTotal = computed(() => props.lostReasons.reduce((sum, r) => sum + r.count, 0))

// Offene Angebote sind eine Prognose und stehen schraffiert, nicht als zweite
// gefuellte Flaeche — IBCS-Notation, damit Ist und Erwartung sich nicht
// gleich fest lesen.
// Der Zeitraum steht als Überschrift, nicht nur im Schalter: ein Export oder
// ein Screenshot trägt ihn sonst nicht mit.
function barWidth(value: number, peak: number) {
  return `${Math.round((value / Math.max(1, peak)) * 100)}%`
}

const windowLabel = computed(() => {
  const format = (date: string) =>
    new Date(date).toLocaleDateString('de-DE', { month: 'short', year: 'numeric' })

  return `${format(props.window.from)} – ${format(props.window.to)}`
})

const stepPeak = computed(() => Math.max(1, ...props.steps.map((s) => s.leads)))
const decimal = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 1 })
const dwellPeak = computed(() => Math.max(1, ...props.dwell.map((d) => d.days)))
const replyPeak = computed(() => Math.max(1, ...props.responseTimes.buckets.map((b) => b.leads)))
const leadTimePeak = computed(() => Math.max(1, ...props.leadTimes.buckets.map((b) => b.projects)))
const shootPeak = computed(() => Math.max(1, ...props.shootsByMonth.map((m) => m.shoots)))
const typePeak = computed(() => Math.max(1, ...props.revenueByType.map((t) => t.average)))
const shootsTotal = computed(() => props.shootsByMonth.reduce((sum, m) => sum + m.shoots, 0))
const biggestDrop = computed(() =>
  props.steps.reduce<(typeof props.steps)[number] | null>(
    (worst, step) => (worst === null || step.lost > worst.lost ? step : worst),
    null,
  ),
)

const forecastFill = 'repeating-linear-gradient(45deg, var(--line-hover) 0 2px, var(--bg) 2px 5px)'
</script>

<template>
  <AdminLayout active="auswertungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 pr-8 max-[640px]:pr-0">
          <PageHead :kicker="$t('reports.title')" :title="windowLabel" />

          <div class="flex min-w-0 flex-wrap items-center gap-2.5 pb-1 max-[640px]:px-5">
            <Select
              :model-value="filters.type ?? 'all'"
              @update:model-value="(value) => filter({ typ: String(value) === 'all' ? null : String(value) })"
            >
              <!-- sm ist die Variante für Schalter in einer Zeile statt in einem
                   Formular; die 30px sind die Höhe der Segmentleiste daneben. -->
              <SelectTrigger id="report-type" size="sm" class="h-[30px] w-[190px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{{ $t('reports.allTypes') }}</SelectItem>
                <SelectItem v-for="type in projectTypes" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
              </SelectContent>
            </Select>

            <ChoiceGroup
              :model-value="filters.period"
              variant="segmented"
              :options="periods"
              @update:model-value="(value) => filter({ zeitraum: value })"
            />
          </div>
        </div>

        <div class="px-6 pt-7 pb-8">
          <!-- Nothing on this screen is entered here: every figure comes out of
               the funnel, so a studio without history gets the funnel's own
               control rather than nine cards reporting no data. -->
          <Empty v-if="bare" :text="$t('reports.empty')">
            <Button :as="Link" href="/anfragen/neu" variant="pill">{{ $t('leads.createKicker') }}</Button>
          </Empty>

          <!-- Same rule as the Übersicht's tiles: a figure with nothing behind it
               is absent, never a zero. A studio with no history reads as empty,
               which is true, rather than as failing. -->
          <div v-if="!bare" class="mb-6 grid grid-cols-4 gap-4 max-[1080px]:grid-cols-2">
            <ReportKpi
              v-if="bookingRate !== null"
              :label="$t('reports.bookingRate')"
              :value="`${bookingRate} %`"
              :sub="$t('reports.ofCaptured', capturedTotal)"
            />
            <ReportKpi
              v-if="acceptanceRate !== null"
              :label="$t('reports.acceptanceRate')"
              :value="`${acceptanceRate} %`"
              :delta="acceptanceDelta === null ? undefined : $t('reports.vsPriorPoints', { delta: signed(acceptanceDelta) })"
              :tone="acceptanceDelta === null ? undefined : tone(acceptanceDelta)"
            />
            <ReportKpi
              v-if="revenue.projects"
              :label="$t('reports.revenue')"
              :value="formatEurosRounded(revenue.revenue)"
              :delta="revenueDelta === null ? undefined : $t('reports.vsPrior', { delta: signed(revenueDelta) })"
              :tone="revenueDelta === null ? undefined : tone(revenueDelta)"
            />
            <ReportKpi
              v-if="revenue.averagePerProject !== null"
              :label="$t('reports.averagePerProject')"
              :value="formatEurosRounded(revenue.averagePerProject)"
              :sub="$t('reports.bookedProjects', { count: revenue.projects })"
            />
          </div>

          <!-- Die beiden Diagramme teilen sich die Breite zur Hälfte. Multi-Column
               kennt bei column-span nur ganz oder gar nicht, also steht die erste
               Reihe im Grid und der Rest darunter im Masonry. -->
          <div v-if="!bare" class="mb-6 grid grid-cols-2 gap-6 max-[640px]:grid-cols-1">
            <ReportCard
              v-if="capturedTotal"
              :title="$t('reports.funnelTitle')"
              :sub="$t('reports.funnelSub', { captured: $t('reports.capturedCount', capturedTotal), booked: bookedTotal })"
            >
              <ul v-if="funnelSparse" class="m-0 flex list-none flex-col gap-1.5 p-0">
                <li v-for="row in funnelByMonth.filter((m) => m.captured)" :key="row.month" class="flex items-baseline justify-between gap-3 text-ui">
                  <span>{{ monthLong(row.month) }}</span>
                  <span class="tabular-nums">{{ $t('reports.funnelSub', { captured: $t('reports.capturedCount', row.captured), booked: row.booked }) }}</span>
                </li>
              </ul>

              <template v-else>
              <div class="flex items-center gap-4 text-small text-muted-foreground">
                <span class="inline-flex items-center gap-1.5">
                  <span class="size-2.5 rounded-[3px] bg-[var(--accent-bg)]" />{{ $t('reports.captured') }}
                </span>
                <span v-if="!cashSparse" class="inline-flex items-center gap-1.5">
                  <span class="size-2.5 rounded-[3px] bg-action" />{{ $t('reports.booked') }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                  <span class="h-0.5 w-3.5 rounded-sm bg-[var(--due)]" />{{ $t('reports.rate') }}
                </span>
              </div>

              <div class="relative mt-auto h-[216px]">
                <div class="absolute inset-0 flex flex-col justify-between">
                  <div class="border-t border-dashed border-border" />
                  <div class="border-t border-dashed border-border" />
                  <div class="border-t border-dashed border-border" />
                  <div class="border-t border-border" />
                </div>
                <svg
                  v-if="ratePoints"
                  class="absolute inset-0 h-full w-full"
                  viewBox="0 0 1000 216"
                  preserveAspectRatio="none"
                  fill="none"
                  role="img"
                  :aria-label="$t('reports.rate')"
                >
                  <polyline
                    :points="ratePoints"
                    stroke="var(--due)"
                    stroke-width="2"
                    stroke-dasharray="5 4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    vector-effect="non-scaling-stroke"
                  />
                </svg>
                <div class="absolute inset-0 flex items-end">
                  <div v-for="row in funnelByMonth" :key="row.month" class="flex min-w-0 flex-1 items-end justify-center gap-1.5 max-[640px]:gap-0.5">
                    <div class="flex flex-col items-center gap-1">
                      <span class="text-small leading-none font-semibold tabular-nums text-muted-foreground">{{ row.captured || '' }}</span>
                      <div class="w-4 rounded-t-[4px] bg-[var(--accent-bg)] max-[640px]:w-2" :style="{ height: barHeight(row.captured, funnelPeak, 188) }" />
                    </div>
                    <div class="flex flex-col items-center gap-1">
                      <span class="text-small leading-none font-semibold tabular-nums text-action">{{ row.booked || '' }}</span>
                      <div class="w-4 rounded-t-[4px] bg-action max-[640px]:w-2" :style="{ height: barHeight(row.booked, funnelPeak, 188) }" />
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex">
                <div v-for="(row, index) in funnelByMonth" :key="row.month" class="flex min-w-0 flex-1 flex-col items-center text-small leading-tight text-muted-foreground">
                  <span :class="!monthShown(row.month, index) && 'max-[640px]:invisible'">{{ monthShort(row.month) }}</span>
                  <span class="h-[13px]">{{ yearTick(row.month, index) }}</span>
                </div>
              </div>
              </template>
            </ReportCard>

            <!-- M-07. Its own panel on this screen rather than a screen of its
                 own: "how are we doing" and "what is coming in" are the same
                 question asked twice. -->
            <ReportCard
              v-if="cashFlow.months.length"
              :title="cashAhead ? $t('reports.cashFlowTitle') : $t('reports.cashFlowPastTitle')"
              :sub="cashAhead
                ? $t('reports.cashFlowSub', { projects: $t('reports.cashProjects', cashFlow.projects), quotes: $t('reports.cashQuotes', cashFlow.quotes) })
                : $t('reports.cashProjects', cashFlowPast.projects)"
            >
              <template #head>
                <ChoiceGroup
                  v-model="cashMode"
                  variant="segmented"
                  :options="[
                    { value: 'expected', label: $t('reports.cashAhead') },
                    { value: 'past', label: $t('reports.cashPast') },
                  ]"
                />
              </template>

              <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-small text-muted-foreground">
                <span class="inline-flex items-center gap-1.5">
                  <span class="size-2.5 rounded-[3px] bg-action" />
                  {{ cashAhead ? $t('reports.seriesDue') : $t('reports.seriesReceived') }}
                </span>
                <span v-if="cashAhead && !cashSparse" class="inline-flex items-center gap-1.5">
                  <span class="size-2.5 rounded-[3px]" :style="{ background: forecastFill }" />{{ $t('reports.seriesOpen') }}
                </span>
                <span
                  v-if="cashAhead && cashFlow.overdue && cashRows.length"
                  class="ml-auto inline-flex items-center gap-1.5 rounded-[var(--r-sm)] bg-late-bg px-2.5 py-1 text-small leading-none font-semibold text-destructive"
                >
                  {{ $t('reports.overdue', { amount: formatEurosRounded(cashFlow.overdue) }) }}
                </span>
              </div>

              <ul v-if="cashSparse" class="m-0 flex list-none flex-col gap-1.5 p-0">
                <li v-if="cashAhead && cashFlow.overdue && !cashRows.length" class="text-ui font-semibold text-destructive">
                  {{ $t('reports.overdue', { amount: formatEurosRounded(cashFlow.overdue) }) }}
                </li>
                <li v-for="row in cashRows" :key="row.month" class="flex items-baseline justify-between gap-3 text-ui">
                  <span>{{ monthLong(row.month) }}</span>
                  <span class="tabular-nums">
                    <template v-if="row.value">{{ formatEurosRounded(row.value) }}</template>
                    <template v-if="row.value && row.forecast"> · </template>
                    <template v-if="row.forecast">{{ $t('reports.openInMonth', { amount: formatEurosRounded(row.forecast) }) }}</template>
                  </span>
                </li>
              </ul>

              <template v-else>
              <!-- Ein einzelner Monat zieht seine Spalte sonst ueber die ganze
                   Karte und behauptet eine Masse, die nicht da ist. -->
              <div class="relative mt-auto h-[216px]">
                <div class="absolute inset-0 flex flex-col justify-between">
                  <div class="border-t border-dashed border-border" />
                  <div class="border-t border-dashed border-border" />
                  <div class="border-t border-dashed border-border" />
                  <div class="border-t border-border" />
                </div>
                <!-- IBCS: die Prognose steht neben dem Ist, nicht darauf, und
                     schmaler als es. Gestapelt läse sich beides als eine Summe,
                     die so niemand erwarten darf. -->
                <div class="absolute inset-0 flex items-end justify-center gap-2.5 max-[640px]:gap-1">
                  <div v-for="row in cashMonths" :key="row.month" class="flex h-full max-w-[64px] flex-1 items-end gap-[3px]">
                    <div class="flex h-full min-w-0 flex-[3] flex-col justify-end gap-1">
                      <span class="text-center text-small leading-none font-semibold tabular-nums whitespace-nowrap text-action max-[640px]:hidden">
                        {{ row.value ? formatEurosRounded(row.value) : '' }}
                      </span>
                      <div class="w-full rounded-t-[4px] bg-action" :style="{ height: barHeight(row.value, cashPeak, 194) }" />
                    </div>
                    <div v-if="cashAhead" class="flex h-full min-w-0 flex-[2] flex-col justify-end gap-1">
                      <span class="text-center text-small leading-none font-semibold tabular-nums whitespace-nowrap text-muted-foreground max-[640px]:hidden">
                        {{ row.forecast ? formatEurosRounded(row.forecast) : '' }}
                      </span>
                      <div v-if="row.forecast" class="w-full rounded-t-[4px]" :style="{ background: forecastFill, height: barHeight(row.forecast, cashPeak, 194) }" />
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex justify-center gap-2.5 max-[640px]:gap-1">
                <div v-for="(row, index) in cashMonths" :key="row.month" class="flex max-w-[64px] flex-1 flex-col items-center text-small leading-tight text-muted-foreground">
                  <span :class="!monthShown(row.month, index) && 'max-[640px]:invisible'">{{ monthShort(row.month) }}</span>
                  <span class="h-[13px]">{{ yearTick(row.month, index) }}</span>
                </div>
              </div>
              </template>
            </ReportCard>
          </div>

          <div v-if="!bare" class="columns-3 gap-6 max-[1080px]:columns-2 max-[640px]:columns-1">
            <ReportCard class="mb-6 break-inside-avoid" v-if="sources.length || referrers.length" :title="$t('reports.sourcesTitle')">
              <div v-if="sources.length" class="flex flex-col gap-3.5">
                <div v-for="row in sources" :key="row.source" class="flex flex-col gap-1.5">
                  <div class="flex items-baseline justify-between gap-3">
                    <span class="text-ui font-medium">{{ row.label }}</span>
                    <span class="text-ui font-semibold tabular-nums">{{ formatEurosRounded(row.revenue) }}</span>
                  </div>
                  <div class="h-1.5 overflow-hidden rounded-[3px] bg-muted">
                    <div class="h-1.5 rounded-[3px] bg-action" :style="{ width: `${Math.round((row.revenue / sourcePeak) * 100)}%` }" />
                  </div>
                  <span class="text-small tabular-nums text-muted-foreground">
                    {{ $t('reports.funnelSub', { captured: $t('reports.capturedCount', row.captured), booked: row.booked }) }}
                  </span>
                </div>
              </div>

              <div v-if="referrers.length" class="mt-auto flex flex-col gap-2 border-t border-border pt-3.5">
                <span class="text-small leading-none font-semibold text-muted-foreground">{{ $t('reports.referrersTitle') }}</span>
                <ul class="m-0 flex list-none flex-col gap-1.5 p-0">
                  <li v-for="row in referrers" :key="row.name" class="flex items-baseline justify-between gap-3 text-small">
                    <span>{{ row.name }}</span>
                    <span class="tabular-nums text-muted-foreground">{{ row.count }}</span>
                  </li>
                </ul>
              </div>
            </ReportCard>

            <!-- The field P-01 has collected since the funnel existed and no
                 screen has ever shown. A lead lost without a reason is in the
                 funnel above and absent here rather than bucketed as
                 "unbekannt" — a category invented for an empty field makes a
                 breakdown look complete when it is not. -->
            <ReportCard class="mb-6 break-inside-avoid" v-if="lostReasons.length" :title="$t('reports.lostTitle')">
              <div class="flex flex-col gap-3.5">
                <div v-for="row in lostReasons" :key="`${row.outcome}:${row.reason}`" class="flex items-center gap-3">
                  <span class="w-[132px] shrink-0 text-right text-small">{{ row.reason }}</span>
                  <div class="flex flex-1 items-center gap-2">
                    <div class="h-4 rounded-[2px] bg-action" :style="{ width: `${Math.round((row.count / lostPeak) * 76)}%` }" />
                    <span class="text-small font-semibold tabular-nums">{{ row.count }}</span>
                  </div>
                </div>
                <div class="flex items-center gap-3 border-t border-border pt-2.5">
                  <span class="w-[132px] shrink-0 text-right text-small font-semibold">{{ $t('reports.total') }}</span>
                  <span class="text-small font-semibold tabular-nums">{{ lostTotal }}</span>
                </div>
              </div>
            </ReportCard>

            <ReportCard class="mb-6 break-inside-avoid" v-if="perContact.length" :title="$t('reports.perContactTitle')">
              <table class="w-full border-collapse text-ui">
                <thead>
                  <tr>
                    <th class="pb-2 text-left text-small leading-none font-semibold text-muted-foreground">{{ $t('reports.contact') }}</th>
                    <th class="pb-2 pl-4 text-right text-small leading-none font-semibold text-muted-foreground">{{ $t('reports.projects') }}</th>
                    <th class="pb-2 pl-4 text-right text-small leading-none font-semibold text-muted-foreground">{{ $t('reports.revenue') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in perContact" :key="row.name">
                    <td class="border-t border-border py-2">
                      <span class="inline-flex items-center gap-2.5">
                        <span class="inline-flex size-[26px] items-center justify-center rounded-full bg-[var(--accent-bg)] text-small font-semibold text-ring">
                          {{ initials(row.name) }}
                        </span>
                        {{ row.name }}
                      </span>
                    </td>
                    <td class="border-t border-border py-2 pl-4 text-right tabular-nums text-muted-foreground">{{ row.projects }}</td>
                    <td class="border-t border-border py-2 pl-4 text-right font-semibold tabular-nums">{{ formatEurosRounded(row.revenue) }}</td>
                  </tr>
                </tbody>
              </table>
            </ReportCard>

            <!-- Was der Trichter zwischen den Schritten verliert und wie lange
                 er dafür braucht. Aus demselben Fenster wie die Karten darüber,
                 nur aus dem Audit-Trail statt aus der Anfrage-Zeile. -->
            <ReportCard class="mb-6 break-inside-avoid" v-if="hasSteps" :title="$t('reports.stepsTitle')" :sub="$t('reports.stepsSub')">
              <div class="flex flex-col gap-3">
                <div v-for="step in steps" :key="step.key" class="flex flex-col gap-1.5">
                  <div class="flex items-baseline justify-between gap-3">
                    <span class="text-small font-medium">{{ step.label }}</span>
                    <span class="text-small font-semibold tabular-nums">{{ step.leads }}</span>
                  </div>
                  <div class="h-2 overflow-hidden rounded-[4px] bg-muted">
                    <div class="h-2 rounded-[4px] bg-action" :style="{ width: barWidth(step.leads, stepPeak) }" />
                  </div>
                  <span v-if="step.drop === null || step.lost" class="text-small tabular-nums" :class="step.lost ? 'text-destructive' : 'text-muted-foreground'">
                    {{ step.drop === null ? $t('reports.stepEntry') : $t('reports.stepDrop', { lost: step.lost, percent: step.drop }) }}
                  </span>
                </div>
              </div>

              <div v-if="biggestDrop" class="mt-auto border-t border-border pt-3 text-small text-muted-foreground">
                {{ $t('reports.biggestDrop', { step: biggestDrop.label, lost: $t('reports.capturedCount', biggestDrop.lost) }) }}
              </div>
            </ReportCard>

            <ReportCard class="mb-6 break-inside-avoid" v-if="revenueByType.length" :title="$t('reports.perTypeTitle')" :sub="$t('reports.perTypeSub')">
              <div class="flex flex-col gap-3.5">
                <div v-for="row in revenueByType" :key="row.type" class="flex flex-col gap-1.5">
                  <div class="flex items-baseline justify-between gap-3">
                    <span class="text-small font-medium">{{ row.label }}</span>
                    <span class="text-ui font-semibold tabular-nums">{{ formatEurosRounded(row.average) }}</span>
                  </div>
                  <div class="h-1.5 overflow-hidden rounded-[3px] bg-muted">
                    <div class="h-1.5 rounded-[3px] bg-action" :style="{ width: barWidth(row.average, typePeak) }" />
                  </div>
                  <span class="text-small tabular-nums text-muted-foreground">
                    {{ $t('reports.bookedProjects', { count: row.projects }) }} · {{ formatEurosRounded(row.revenue) }}
                  </span>
                </div>
              </div>
            </ReportCard>

            <ReportCard class="mb-6 break-inside-avoid" v-if="dwell.length" :title="$t('reports.dwellTitle')" :sub="$t('reports.dwellSub')">
              <div class="flex flex-col gap-3">
                <div v-for="row in dwell" :key="row.key" class="flex items-center gap-3">
                  <span class="w-[104px] shrink-0 text-small">{{ row.label }}</span>
                  <div class="h-2 flex-1 overflow-hidden rounded-[4px] bg-muted">
                    <div
                      class="h-2 rounded-[4px]"
                      :class="row.days >= dwellPeak ? 'bg-[var(--due)]' : 'bg-action'"
                      :style="{ width: barWidth(row.days, dwellPeak) }"
                    />
                  </div>
                  <span class="w-12 shrink-0 text-right text-small font-semibold tabular-nums">
                    {{ $t('reports.days', { days: decimal.format(row.days) }) }}
                  </span>
                </div>
              </div>
            </ReportCard>

            <ReportCard class="mb-6 break-inside-avoid" v-if="leadTimes.median !== null" :title="$t('reports.leadTimeTitle')" :sub="$t('reports.leadTimeSub')">
              <template #head>
                <span class="rounded-[var(--r-sm)] bg-[var(--accent-bg)] px-2.5 py-1 text-small leading-none font-semibold text-ring">
                  {{ $t('reports.median', { value: leadTimes.median }) }}
                </span>
              </template>

              <div class="flex flex-col gap-3">
                <div v-for="bucket in leadTimes.buckets" :key="bucket.key" class="flex items-center gap-3">
                  <span class="w-[104px] shrink-0 text-small">{{ bucket.label }}</span>
                  <div class="h-2 flex-1 overflow-hidden rounded-[4px] bg-muted">
                    <div class="h-2 rounded-[4px] bg-action" :style="{ width: barWidth(bucket.projects, leadTimePeak) }" />
                  </div>
                  <span class="w-7 shrink-0 text-right text-small font-semibold tabular-nums">{{ bucket.projects }}</span>
                </div>
              </div>

              <div v-if="leadTimes.withoutDate" class="mt-auto border-t border-border pt-3 text-small text-muted-foreground">
                {{ $t('reports.withoutDate', { count: leadTimes.withoutDate }) }}
              </div>
            </ReportCard>

            <ReportCard class="mb-6 break-inside-avoid" v-if="responseTimes.medianHours !== null" :title="$t('reports.responseTitle')" :sub="$t('reports.responseSub')">
              <template #head>
                <span class="rounded-[var(--r-sm)] bg-[var(--accent-bg)] px-2.5 py-1 text-small leading-none font-semibold text-ring">
                  {{ $t('reports.medianHours', { hours: decimal.format(responseTimes.medianHours) }) }}
                </span>
              </template>

              <div class="flex flex-col gap-3">
                <div v-for="bucket in responseTimes.buckets" :key="bucket.key" class="flex items-center gap-3">
                  <span class="w-[104px] shrink-0 text-small">{{ bucket.label }}</span>
                  <div class="h-2 flex-1 overflow-hidden rounded-[4px] bg-muted">
                    <div
                      class="h-2 rounded-[4px]"
                      :class="bucket.key === 'later' ? 'bg-[var(--due)]' : 'bg-action'"
                      :style="{ width: barWidth(bucket.leads, replyPeak) }"
                    />
                  </div>
                  <span class="w-7 shrink-0 text-right text-small font-semibold tabular-nums">{{ bucket.leads }}</span>
                </div>
              </div>

              <div v-if="responseTimes.unanswered" class="mt-auto border-t border-border pt-3 text-small text-muted-foreground">
                {{ $t('reports.unanswered', { count: responseTimes.unanswered }) }}
              </div>
            </ReportCard>

            <ReportCard class="mb-6 break-inside-avoid" v-if="shootsTotal" :title="$t('reports.shootsTitle')" :sub="$t('reports.shootsSub', { count: shootsTotal })">
              <ul v-if="shootsSparse" class="m-0 flex list-none flex-col gap-1.5 p-0">
                <li v-for="row in shootsByMonth.filter((m) => m.shoots)" :key="row.month" class="flex items-baseline justify-between gap-3 text-ui">
                  <span>{{ monthLong(row.month) }}</span>
                  <span class="tabular-nums">{{ $t('reports.shootsSub', { count: row.shoots }) }}</span>
                </li>
              </ul>
              <template v-else>
              <div class="flex h-[120px] items-end justify-center gap-1.5">
                <div v-for="row in shootsByMonth" :key="row.month" class="flex h-full max-w-[40px] min-w-0 flex-1 flex-col justify-end gap-1">
                  <span class="text-center text-small leading-none font-semibold tabular-nums text-muted-foreground">{{ row.shoots || '' }}</span>
                  <div class="w-full rounded-t-[4px] bg-action" :style="{ height: barHeight(row.shoots, shootPeak, 92) }" />
                </div>
              </div>
              <div class="flex justify-center gap-1.5">
                <div v-for="(row, index) in shootsByMonth" :key="row.month" class="flex max-w-[40px] min-w-0 flex-1 flex-col items-center text-small leading-tight text-muted-foreground">
                  <span :class="!monthShown(row.month, index) && 'invisible'">{{ monthShort(row.month) }}</span>
                  <span class="h-[13px]">{{ yearTick(row.month, index) }}</span>
                </div>
              </div>
              </template>
            </ReportCard>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
