<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import ReportCard from '@/admin/components/ReportCard.vue'
import ReportKpi from '@/admin/components/ReportKpi.vue'
import { Button } from '@/ui/button'
import { formatEurosRounded } from '@/admin/lib/money'

const props = defineProps<{
  window: { from: string; to: string }
  funnelByMonth: { month: string; captured: number; booked: number; rate: number | null }[]
  sources: { source: string; label: string; captured: number; booked: number; revenue: number }[]
  referrers: { name: string; count: number }[]
  lostReasons: { outcome: string; outcomeLabel: string; reason: string; count: number }[]
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
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('reports.window')" :title="$t('reports.title')" />

        <div class="px-8 pt-7 pb-8">
          <!-- Nothing on this screen is entered here: every figure comes out of
               the funnel, so a studio without history gets the funnel's own
               control rather than nine cards reporting no data. -->
          <Empty v-if="bare" :text="$t('reports.empty')">
            <Button :as="Link" href="/anfragen/neu" variant="pill">{{ $t('leads.createKicker') }}</Button>
          </Empty>

          <!-- Same rule as the Übersicht's tiles: a figure with nothing behind it
               is absent, never a zero. A studio with no history reads as empty,
               which is true, rather than as failing. -->
          <div v-if="!bare" class="mb-6 grid grid-cols-4 gap-2.5">
            <ReportKpi v-if="bookingRate !== null">
              <b>{{ bookingRate }} %</b>
              {{ $t('reports.bookingRate') }}
              <em> · {{ $t('reports.ofCaptured', { count: capturedTotal }) }}</em>
            </ReportKpi>
            <ReportKpi v-if="acceptanceRate !== null">
              <b>{{ acceptanceRate }} %</b>
              {{ $t('reports.acceptanceRate') }}
              <em v-if="acceptanceDelta !== null"> · {{ $t('reports.vsPriorPoints', { delta: acceptanceDelta > 0 ? `+${acceptanceDelta}` : acceptanceDelta }) }}</em>
            </ReportKpi>
            <ReportKpi v-if="revenue.projects">
              <b>{{ formatEurosRounded(revenue.revenue) }}</b>
              {{ $t('reports.revenue') }}
              <em v-if="revenueDelta !== null"> · {{ $t('reports.vsPrior', { delta: revenueDelta > 0 ? `+${revenueDelta}` : revenueDelta }) }}</em>
            </ReportKpi>
            <ReportKpi v-if="revenue.averagePerProject !== null">
              <b>{{ formatEurosRounded(revenue.averagePerProject) }}</b>
              {{ $t('reports.averagePerProject') }}
              <em> · {{ $t('reports.bookedProjects', { count: revenue.projects }) }}</em>
            </ReportKpi>
          </div>

          <div v-if="!bare" class="grid grid-cols-[minmax(0,1fr)_296px] items-start gap-8">
            <div>
              <ReportCard v-if="funnelByMonth.length" :title="$t('reports.funnelTitle')">
                <table class="mt-2.5 w-full border-collapse text-[13px] leading-[1.5] [&_th]:pb-1.5 [&_th]:text-left [&_th]:text-[12px] [&_th]:leading-none [&_th]:font-medium [&_th]:text-muted-foreground [&_td]:border-t [&_td]:border-border [&_td]:py-1">
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
                        <!-- Ein Balken hinter der Zahl statt einer Chart-Bibliothek: eine Spalte
                             Proportionen ist die ganze Anforderung. --fill kommt aus dem Markup. -->
                        <span
                          class="relative isolate block h-[3px] overflow-hidden rounded-[2px] bg-muted px-1.5 py-0.5 before:absolute before:top-0 before:bottom-0 before:left-0 before:right-auto before:-z-10 before:w-[var(--fill)] before:rounded-[3px] before:bg-[var(--accent-weak,rgb(0_0_0/6%))] before:content-['']"
                          :style="{ '--fill': `${(row.captured / funnelPeak) * 100}%` }"
                        >{{ row.captured }}</span>
                      </td>
                      <td>{{ row.booked }}</td>
                      <td>{{ row.rate === null ? '—' : `${row.rate} %` }}</td>
                    </tr>
                  </tbody>
                </table>
              </ReportCard>

              <ReportCard v-if="sources.length || referrers.length" class="mt-4" :title="$t('reports.sourcesTitle')">
                <table v-if="sources.length" class="mt-2.5 w-full border-collapse text-[13px] leading-[1.5] [&_th]:pb-1.5 [&_th]:text-left [&_th]:text-[12px] [&_th]:leading-none [&_th]:font-medium [&_th]:text-muted-foreground [&_td]:border-t [&_td]:border-border [&_td]:py-1">
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
                      <td>{{ formatEurosRounded(row.revenue) }}</td>
                    </tr>
                  </tbody>
                </table>

                <template v-if="referrers.length">
                  <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint mt-4">{{ $t('reports.referrersTitle') }}</div>
                  <ul class="mt-2.5 list-none p-0 text-[13px] leading-[1.7]">
                    <li v-for="row in referrers" :key="row.name">
                      {{ row.name }} — {{ $t('reports.referralCount', { count: row.count }) }}
                    </li>
                  </ul>
                </template>
              </ReportCard>

              <!-- The field P-01 has collected since the funnel existed and no
                   screen has ever shown. A lead lost without a reason is in the
                   funnel above and absent here rather than bucketed as
                   "unbekannt" — a category invented for an empty field makes a
                   breakdown look complete when it is not. -->
              <ReportCard v-if="lostReasons.length" class="mt-4" :title="$t('reports.lostTitle')">
                <ul class="mt-2.5 list-none p-0 text-[13px] leading-[1.7]">
                  <li v-for="row in lostReasons" :key="`${row.outcome}:${row.reason}`">
                    {{ row.outcomeLabel }} · {{ row.reason }} — {{ row.count }}
                  </li>
                </ul>
              </ReportCard>
            </div>

            <div>
              <!-- M-07. Its own panel on this screen rather than a screen of its
                   own: "how are we doing" and "what is coming in" are the same
                   question asked twice. -->
              <ReportCard v-if="cashFlow.months.length" :title="$t('reports.cashFlowTitle')">
                <p v-if="cashFlow.overdue" class="text-[11.5px] leading-[1.45] font-normal text-destructive">
                  {{ $t('reports.overdue', { amount: formatEurosRounded(cashFlow.overdue) }) }}
                </p>

                <table class="mt-2.5 w-full border-collapse text-[13px] leading-[1.5] [&_th]:pb-1.5 [&_th]:text-left [&_th]:text-[12px] [&_th]:leading-none [&_th]:font-medium [&_th]:text-muted-foreground [&_td]:border-t [&_td]:border-border [&_td]:py-1">
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
                        <span
                          class="relative isolate block h-[3px] overflow-hidden rounded-[2px] bg-muted px-1.5 py-0.5 before:absolute before:top-0 before:bottom-0 before:left-0 before:right-auto before:-z-10 before:w-[var(--fill)] before:rounded-[3px] before:bg-[var(--accent-weak,rgb(0_0_0/6%))] before:content-['']"
                          :style="{ '--fill': `${(row.due / cashPeak) * 100}%` }"
                        >
                          {{ row.due ? formatEurosRounded(row.due) : '—' }}
                        </span>
                      </td>
                      <td>{{ row.received ? formatEurosRounded(row.received) : '—' }}</td>
                      <td>{{ row.open ? formatEurosRounded(row.open) : '—' }}</td>
                    </tr>
                  </tbody>
                </table>
                <p class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('reports.cashFlowNote') }}</p>
              </ReportCard>

              <ReportCard v-if="perContact.length" class="mt-4" :title="$t('reports.perContactTitle')">
                <table class="mt-2.5 w-full border-collapse text-[13px] leading-[1.5] [&_th]:pb-1.5 [&_th]:text-left [&_th]:text-[12px] [&_th]:leading-none [&_th]:font-medium [&_th]:text-muted-foreground [&_td]:border-t [&_td]:border-border [&_td]:py-1">
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
                      <td>{{ formatEurosRounded(row.revenue) }}</td>
                    </tr>
                  </tbody>
                </table>
              </ReportCard>
            </div>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
