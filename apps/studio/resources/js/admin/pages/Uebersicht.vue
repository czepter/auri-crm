<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Metric from '@/admin/components/Metric.vue'
import type { Tone } from '@/admin/lib/metricDots'

const props = defineProps<{
  today: string
  nextShoot: App.Crm.Data.NextShootData | null
  week: { when: string; what: string; away: boolean }[]
  tasks: App.Crm.Data.TaskRowData[]
  openTasks: number
  inquiries: App.Crm.Data.DomainRowData[]
  payments: App.Crm.Data.DomainRowData[]
  galleries: App.Crm.Data.DomainRowData[]
  newLeads: App.Crm.Data.LeadCardData[]
  pipeline: number
  pipelineStages: string[]
  overdue: { total: number; open: number; all: number; sinceDays: number | null }
  quotes: { sent: number; accepted: number }
  unanswered: number
}>()

// Quote.total and PaymentInstallment.amount are minor units end to end; this
// is the one place they turn into euros, so no other spot on this screen
// divides by 100 again.
const money = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })

const heading = computed(() =>
  new Date(props.today).toLocaleDateString('de-DE', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }),
)

// Which shade a funnel stage gets. Four shades for six stages, so the last
// three share the pale one — the row shows movement through the funnel, not a
// legend of it.
const STAGE_TONES: Record<string, Tone> = {
  new: 'on',
  contacted: 's2',
  quote_sent: 's3',
}

const pipelineDots = computed<Tone[]>(() => props.pipelineStages.map((stage) => STAGE_TONES[stage] ?? ''))

const overdueDots = computed<Tone[]>(() => [
  ...Array.from<unknown, Tone>({ length: props.overdue.open }, () => 'warn'),
  ...Array.from<unknown, Tone>({ length: Math.max(0, props.overdue.all - props.overdue.open) }, () => 'on'),
])

const quoteDots = computed<Tone[]>(() => [
  ...Array.from<unknown, Tone>({ length: props.quotes.accepted }, () => 'on'),
  ...Array.from<unknown, Tone>({ length: Math.max(0, props.quotes.sent - props.quotes.accepted) }, () => ''),
])

const readinessDots = computed<Tone[]>(() =>
  (props.nextShoot?.readiness ?? []).map((mark) => (mark.done ? 'on' : 'warn')),
)

const openPoints = computed(() => (props.nextShoot?.readiness ?? []).filter((mark) => !mark.done).length)

// The day's five decisions, from every source that owes one: a task, an offer
// waiting on the studio, money the machine has already chased. Sorted so the
// marked ones lead — a row that is late is the one to press first.
const decisions = computed(() => {
  const fromTasks = props.tasks.map((task) => ({
    key: `task:${task.id}`,
    who: task.projectName,
    href: `/auftraege/${task.projectId}`,
    what: task.title,
    emphasis: task.isOverdue ? dayOf(task.dueOn) : null,
    whatTail: !task.isOverdue && task.dueOn ? ` · ${dayOf(task.dueOn)}` : null,
    hot: task.isOverdue,
    done: task,
    action: null,
  }))

  const fromRows = [...props.inquiries, ...props.payments].map((row) => ({
    key: `row:${row.projectId}:${row.what}`,
    who: row.who,
    href: `/auftraege/${row.projectId}`,
    what: row.what,
    emphasis: row.emphasis,
    whatTail: row.whatTail,
    hot: row.hot,
    done: null,
    action: row.action,
  }))

  return [...fromTasks, ...fromRows]
    .sort((a, b) => Number(b.hot) - Number(a.hot))
    .slice(0, 5)
})

const owed = computed(() => props.openTasks + props.inquiries.length + props.payments.length)

function dayOf(date: string | null) {
  return date ? new Date(date).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' }) : ''
}

// "Mi 14:00" — the weekday, because a week is read by day, and the time only
// where the entry has one.
function weekWhen(when: string) {
  const [date, time] = when.split(' ')
  const day = new Date(date).toLocaleDateString('de-DE', { weekday: 'short' })

  return time ? `${day} ${time}` : day
}

const heroDay = computed(() => {
  if (props.nextShoot?.date == null) {
    return undefined
  }

  const date = new Date(props.nextShoot.date)

  return {
    number: String(date.getDate()),
    weekday: date.toLocaleDateString('de-DE', { weekday: 'short' }),
    what: props.nextShoot.title,
    who: [props.nextShoot.projectName, props.nextShoot.callTime].filter(Boolean).join(' · '),
  }
})

function complete(task: App.Crm.Data.TaskRowData) {
  router.post(`/aufgaben/${task.id}/erledigt`, {}, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="uebersicht">
    <div data-theme="crm">
      <div class="hrow">
        <div>
          <div class="kick">{{ heading }}</div>
          <h1>{{ $t('dashboard.title') }}</h1>
        </div>

        <!-- Unbeantwortet, not ungelesen: ThreadParticipant carries no
             last_read_at and this screen does not add one. Static, because
             threads exist only per project — routes/admin.php has no
             cross-project inbox to send this to, and inventing a destination
             is the same failure as inventing a number. -->
        <span v-if="unanswered" class="kick" style="margin-left: auto">
          {{ $t('dashboard.unanswered', { count: unanswered }) }}
        </span>
      </div>

      <div class="cgrid">
        <!-- Every tile brings its own proof, or it is not a tile (C-05). A
             figure with nothing behind it is absent rather than a zero. -->
        <Metric v-if="pipeline" :label="$t('dashboard.pipeline')" :value="money.format(pipeline / 100)" :dots="pipelineDots">
          <p><b>{{ $t('dashboard.leadCount', pipelineStages.length) }}</b></p>
        </Metric>

        <Metric
          v-if="overdue.total"
          :label="$t('dashboard.overdueTotal')"
          :value="money.format(overdue.total / 100)"
          warn
          :dots="overdueDots"
        >
          <p>
            <b>{{ $t('dashboard.instalmentsOpen', { open: overdue.open, all: overdue.all }) }}</b>
            <template v-if="overdue.sinceDays !== null"> · {{ $t('dashboard.sinceDays', overdue.sinceDays) }}</template>
          </p>
        </Metric>

        <Metric
          v-if="quotes.sent"
          :label="$t('dashboard.acceptance')"
          :value="String(quotes.accepted)"
          :unit="$t('dashboard.ofSent', { count: quotes.sent })"
          :dots="quoteDots"
        >
          <p>{{ $t('dashboard.lastTwelveMonths') }}</p>
        </Metric>

        <Metric v-if="heroDay" :label="$t('dashboard.nextShoot')" :day="heroDay" :dots="readinessDots">
          <p>
            <b v-if="openPoints">{{ $t('dashboard.openPoints', { count: openPoints, all: readinessDots.length }) }}</b>
            <b v-else>{{ $t('dashboard.allReady') }}</b>
            <template v-if="nextShoot?.venue"> · {{ nextShoot.venue }}</template>
          </p>
        </Metric>

        <!-- The most important field is the only one without a container:
             emphasis comes from position, size and the one filled button. -->
        <section class="panel bare sp3">
          <header>
            <h2>{{ $t('dashboard.decide') }}</h2>
            <span v-if="decisions.length" class="n">{{ decisions.length }}</span>
            <span v-if="owed > decisions.length" class="n" style="margin-left: auto">
              {{ $t('dashboard.moreOwed', { count: owed - decisions.length }) }}
            </span>
          </header>

          <div v-if="decisions.length" class="decide">
            <div v-for="(decision, index) in decisions" :key="decision.key" class="row">
              <Link class="who" :href="decision.href">{{ decision.who }}</Link>
              <span class="what">
                {{ decision.what }}
                <em v-if="decision.emphasis">{{ decision.emphasis }}</em>
                <template v-if="decision.whatTail">{{ decision.whatTail }}</template>
              </span>
              <span class="act">
                <!-- Exactly one filled button on the screen, and it is the due
                     action in the first decision row — not `Anfrage anlegen`,
                     which is never the thing that is due. -->
                <a
                  v-if="decision.action"
                  :class="index === 0 ? 'btn' : 'btn-2'"
                  :href="decision.action.target"
                >{{ decision.action.label }}</a>
                <button
                  v-else-if="decision.done"
                  type="button"
                  :class="index === 0 ? 'btn' : 'btn-2'"
                  @click="complete(decision.done)"
                >{{ $t('dashboard.done') }}</button>
              </span>
            </div>
          </div>

          <!-- A day with nothing to decide is the normal one. The next
               shooting day takes the field's place rather than a heading with
               nothing under it. -->
          <p v-else-if="nextShoot" class="hint">
            {{ $t('dashboard.nothingDueNext', { what: nextShoot.title, date: dayOf(nextShoot.date) }) }}
          </p>
          <p v-else class="hint">{{ $t('dashboard.nothingDue') }}</p>
        </section>

        <section class="panel">
          <header><h2>{{ $t('dashboard.thisWeek') }}</h2></header>
          <div v-if="week.length" class="list">
            <div v-for="entry in week" :key="`${entry.when}${entry.what}`" class="row" :class="{ off: entry.away }">
              <span class="when">{{ weekWhen(entry.when) }}</span>
              <span class="n">{{ entry.away ? $t('dashboard.away', { name: entry.what }) : entry.what }}</span>
            </div>
          </div>
          <p v-else class="hint">{{ $t('dashboard.weekEmpty') }}</p>
        </section>

        <section v-if="newLeads.length" class="panel sp2">
          <header>
            <h2>{{ $t('dashboard.inquiries') }}</h2>
            <span class="n">{{ newLeads.length }}</span>
            <Link class="link more" href="/anfragen">{{ $t('dashboard.toBoard') }}</Link>
          </header>
          <div class="list">
            <div v-for="lead in newLeads" :key="lead.id" class="row">
              <span class="tag">{{ lead.type }}</span>
              <Link class="n" :href="`/anfragen/${lead.leadId}`">{{ lead.names }}</Link>
              <!-- The conflict is a mark on the row that has it and leads into
                   the resolution; a footer counting collisions names neither
                   which nor where. -->
              <Link v-if="lead.conflicts.length" class="flag" :href="`/auftraege/${lead.id}/shootings`">
                {{ $t('dashboard.dateDoubled', { date: dayOf(lead.date) }) }}
              </Link>
              <span v-else class="m">{{ lead.source }}</span>
            </div>
          </div>
        </section>

        <section v-if="galleries.length" class="panel sp2">
          <header>
            <h2>{{ $t('dashboard.galleries') }}</h2>
            <span class="n">{{ galleries.length }}</span>
          </header>
          <div class="list">
            <div v-for="row in galleries" :key="`${row.projectId}${row.what}`" class="row">
              <Link class="n" :href="`/auftraege/${row.projectId}`">{{ row.who }}</Link>
              <span class="m">
                {{ row.what }}
                <template v-if="row.emphasis">{{ row.emphasis }}</template>
                <template v-if="row.whatTail">{{ row.whatTail }}</template>
              </span>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AdminLayout>
</template>
