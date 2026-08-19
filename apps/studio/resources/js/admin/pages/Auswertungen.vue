<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Button } from '@/ui/button'

const props = defineProps<{
  window: { from: string; to: string }
  funnelByMonth: { month: string; captured: number; booked: number; rate: number | null }[]
  sources: { source: string; label: string; captured: number; booked: number; revenue: number }[]
  referrers: { name: string; count: number }[]
  lostReasons: { reason: string; count: number }[]
  acceptanceRate: number | null
  acceptanceRatePrior: number | null
  revenue: { projects: number; revenue: number; averagePerProject: number | null }
  revenuePrior: { projects: number; revenue: number; averagePerProject: number | null }
  perContact: { name: string; projects: number; revenue: number }[]
  cashFlow: { overdue: number; months: { month: string; due: number; received: number; open: number }[] }
}>()

const bare = computed(() =>
  !props.funnelByMonth.length
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
const money = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })

function monthLabel(month: string) {
  const [year, index] = month.split('-')
  return new Date(Number(year), Number(index) - 1, 1)
    .toLocaleDateString('de-DE', { month: 'short', year: '2-digit' })
}

// A Vorjahresvergleich is only meaningful when there is a prior year to
// compare against. Null propagates rather than turning into "+100 %", which
// is what a studio's first year would otherwise read as.
function delta(now: number | null, prior: number | null) {
  if (now === null || prior === null || prior === 0) return null
  return Math.round(((now - prior) / prior) * 100)
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
const cashPeak = computed(() =>
  Math.max(1, ...props.cashFlow.months.map((m) => Math.max(m.due, m.received, m.open))),
)

const bookedTotal = computed(() => props.funnelByMonth.reduce((sum, m) => sum + m.booked, 0))
const capturedTotal = computed(() => props.funnelByMonth.reduce((sum, m) => sum + m.captured, 0))
const bookingRate = computed(() =>
  capturedTotal.value === 0 ? null : Math.round((bookedTotal.value / capturedTotal.value) * 100),
)
</script>

<template>
  <AdminLayout active="auswertungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('reports.window') }}</div>
        <h1>{{ $t('reports.title') }}</h1>
      </div>
    </div>

    <!-- Nothing on this screen is entered here: every figure comes out of
         the funnel, so a studio without history gets the funnel's own
         control rather than nine cards reporting no data. -->
    <Button v-if="bare" :as="Link" href="/anfragen/neu" style="margin-top: var(--s5)">
      {{ $t('leads.createKicker') }}
    </Button>

    <!-- Same rule as the Übersicht's tiles: a figure with nothing behind it
         is absent, never a zero. A studio with no history reads as empty,
         which is true, rather than as failing. -->
    <div v-if="!bare" class="kpis">
      <div v-if="bookingRate !== null" class="kpi">
        <b>{{ bookingRate }} %</b>
        {{ $t('reports.bookingRate') }}
        <em> · {{ $t('reports.ofCaptured', { count: capturedTotal }) }}</em>
      </div>
      <div v-if="acceptanceRate !== null" class="kpi">
        <b>{{ acceptanceRate }} %</b>
        {{ $t('reports.acceptanceRate') }}
        <em v-if="acceptanceDelta !== null"> · {{ $t('reports.vsPriorPoints', { delta: acceptanceDelta > 0 ? `+${acceptanceDelta}` : acceptanceDelta }) }}</em>
      </div>
      <div v-if="revenue.projects" class="kpi">
        <b>{{ money.format(revenue.revenue / 100) }}</b>
        {{ $t('reports.revenue') }}
        <em v-if="revenueDelta !== null"> · {{ $t('reports.vsPrior', { delta: revenueDelta > 0 ? `+${revenueDelta}` : revenueDelta }) }}</em>
      </div>
      <div v-if="revenue.averagePerProject !== null" class="kpi">
        <b>{{ money.format(revenue.averagePerProject / 100) }}</b>
        {{ $t('reports.averagePerProject') }}
        <em> · {{ $t('reports.bookedProjects', { count: revenue.projects }) }}</em>
      </div>
    </div>

    <div v-if="!bare" class="split" style="margin-top: var(--s5)">
      <div class="prail">
        <div v-if="funnelByMonth.length" class="card static">
          <div class="cardhead"><span class="kick">{{ $t('reports.funnelTitle') }}</span></div>
          <table class="tbl">
            <thead>
              <tr>
                <th>{{ $t('reports.month') }}</th>
                <th>{{ $t('reports.captured') }}</th>
                <th>{{ $t('reports.booked') }}</th>
                <th>{{ $t('reports.rate') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in funnelByMonth" :key="row.month">
                <td>{{ monthLabel(row.month) }}</td>
                <td>
                  <span class="bar" :style="{ '--fill': `${(row.captured / funnelPeak) * 100}%` }">{{ row.captured }}</span>
                </td>
                <td>{{ row.booked }}</td>
                <td>{{ row.rate === null ? '—' : `${row.rate} %` }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="sources.length || referrers.length" class="card static" style="margin-top: var(--s4)">
          <div class="cardhead"><span class="kick">{{ $t('reports.sourcesTitle') }}</span></div>
          <table v-if="sources.length" class="tbl">
            <thead>
              <tr>
                <th>{{ $t('reports.source') }}</th>
                <th>{{ $t('reports.captured') }}</th>
                <th>{{ $t('reports.booked') }}</th>
                <th>{{ $t('reports.revenue') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in sources" :key="row.source">
                <td>{{ row.label }}</td>
                <td>{{ row.captured }}</td>
                <td>{{ row.booked }}</td>
                <td>{{ money.format(row.revenue / 100) }}</td>
              </tr>
            </tbody>
          </table>

          <template v-if="referrers.length">
            <div class="dlab" style="margin-top: var(--s4)">{{ $t('reports.referrersTitle') }}</div>
            <ul class="plain">
              <li v-for="row in referrers" :key="row.name">
                {{ row.name }} — {{ $t('reports.referralCount', { count: row.count }) }}
              </li>
            </ul>
          </template>
        </div>

        <!-- The field P-01 has collected since the funnel existed and no
             screen has ever shown. A lead lost without a reason is in the
             funnel above and absent here rather than bucketed as
             "unbekannt" — a category invented for an empty field makes a
             breakdown look complete when it is not. -->
        <div v-if="lostReasons.length" class="card static" style="margin-top: var(--s4)">
          <div class="cardhead"><span class="kick">{{ $t('reports.lostTitle') }}</span></div>
          <ul class="plain">
            <li v-for="row in lostReasons" :key="row.reason">{{ row.reason }} — {{ row.count }}</li>
          </ul>
        </div>
      </div>

      <div class="prail">
        <!-- M-07. Its own panel on this screen rather than a screen of its
             own: "how are we doing" and "what is coming in" are the same
             question asked twice. -->
        <div v-if="cashFlow.months.length" class="card static">
          <div class="cardhead"><span class="kick">{{ $t('reports.cashFlowTitle') }}</span></div>

          <p v-if="cashFlow.overdue" class="err">
            {{ $t('reports.overdue', { amount: money.format(cashFlow.overdue / 100) }) }}
          </p>

          <table class="tbl">
            <thead>
              <tr>
                <th>{{ $t('reports.month') }}</th>
                <th>{{ $t('reports.due') }}</th>
                <th>{{ $t('reports.received') }}</th>
                <th>{{ $t('reports.openQuotes') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in cashFlow.months" :key="row.month">
                <td>{{ monthLabel(row.month) }}</td>
                <td>
                  <span class="bar" :style="{ '--fill': `${(row.due / cashPeak) * 100}%` }">
                    {{ row.due ? money.format(row.due / 100) : '—' }}
                  </span>
                </td>
                <td>{{ row.received ? money.format(row.received / 100) : '—' }}</td>
                <td>{{ row.open ? money.format(row.open / 100) : '—' }}</td>
              </tr>
            </tbody>
          </table>
          <p class="hint">{{ $t('reports.cashFlowNote') }}</p>
        </div>

        <div v-if="perContact.length" class="card static" style="margin-top: var(--s4)">
          <div class="cardhead"><span class="kick">{{ $t('reports.perContactTitle') }}</span></div>
          <table class="tbl">
            <thead>
              <tr>
                <th>{{ $t('reports.contact') }}</th>
                <th>{{ $t('reports.projects') }}</th>
                <th>{{ $t('reports.revenue') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in perContact" :key="row.name">
                <td>{{ row.name }}</td>
                <td>{{ row.projects }}</td>
                <td>{{ money.format(row.revenue / 100) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
/* A bar behind the number rather than a chart library: one column of
   proportions is the whole requirement, and a dependency for it would be
   the kind of weight this codebase adds only when a real chart arrives. */
.bar {
  display: block;
  position: relative;
  padding: 2px 6px;
  isolation: isolate;
}

.bar::before {
  content: '';
  position: absolute;
  inset: 0 auto 0 0;
  width: var(--fill);
  background: var(--accent-weak, rgb(0 0 0 / 6%));
  border-radius: 3px;
  z-index: -1;
}

.tbl {
  width: 100%;
  border-collapse: collapse;
  margin-top: var(--s3);
  font: 400 13px/1.5 var(--font);
}

.tbl th {
  text-align: left;
  font: 500 12px/1 var(--font);
  color: var(--muted);
  padding-bottom: 6px;
}

.tbl td {
  padding: 4px 0;
  border-top: 1px solid var(--line);
}

.plain {
  list-style: none;
  margin: var(--s3) 0 0;
  padding: 0;
  font: 400 13px/1.7 var(--font);
}
</style>
