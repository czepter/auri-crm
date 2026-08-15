<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import DomainColumn from '@/admin/components/DomainColumn.vue'
import { Button } from '@/ui/button'

const props = defineProps<{
  today: string
  openLeads: number
  newLeadsThisWeek: number
  nextShoot: App.Crm.Data.NextShootData | null
  production: App.Crm.Data.ProductionRowData[]
  tasks: App.Crm.Data.TaskRowData[]
  openTasks: number
  inquiries: App.Crm.Data.DomainRowData[]
  payments: App.Crm.Data.DomainRowData[]
  afterDelivery: App.Crm.Data.DomainRowData[]
}>()

const heading = computed(() =>
  new Date(props.today).toLocaleDateString('de-DE', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }),
)

function shortDate(date: string | null) {
  return date
    ? new Date(date).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' })
    : null
}

// The rows the studio still owes. The hero's readiness marks name the same
// tasks, and mockup 01 repeats them on purpose: the mark states, the row acts.
// Filtering the hero's project out instead emptied the worklist entirely for a
// studio with one project in production — a footer counting nine open tasks
// above nothing to press.
const worklist = computed(() => props.tasks)

function complete(task: App.Crm.Data.TaskRowData) {
  router.post(`/aufgaben/${task.id}/erledigt`, {}, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="uebersicht">
    <div class="hrow">
      <div>
        <div class="kick">{{ heading }}</div>
        <h1>{{ $t('dashboard.title') }}</h1>
      </div>
      <Button :as="Link" href="/anfragen/neu">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('board.create') }}
      </Button>
    </div>

    <!-- Two tiles, not four. The mockup's other two are money — quotes are Q-*
         and Stripe is M-04 — and a tile with no source behind it is a number
         somebody has to invent. -->
    <div class="kpis">
      <Link class="kpi" href="/anfragen">
        <b>{{ openLeads }}</b>
        {{ $t('dashboard.openLeads') }}
        <em v-if="newLeadsThisWeek"> · {{ $t('dashboard.newThisWeek', { count: newLeadsThisWeek }) }}</em>
      </Link>
      <Link v-if="nextShoot" class="kpi" :href="`/auftraege/${nextShoot.projectId}/shootings`">
        <b>{{ shortDate(nextShoot.date) }}</b>
        {{ $t('dashboard.nextShoot') }}
        <em v-if="nextShoot.readiness.some((mark) => !mark.done)">
          · {{ $t('dashboard.openPoints', { count: nextShoot.readiness.filter((m) => !m.done).length }) }}
        </em>
      </Link>
    </div>

    <div class="doms">
      <DomainColumn
        v-if="inquiries.length"
        :title="$t('dashboard.inquiries')"
        :count="$t('dashboard.items', { count: inquiries.length })"
        :rows="inquiries"
        :footer="$t('dashboard.toBoard')"
        footer-href="/anfragen"
      />

      <div class="dom">
        <div class="dh">
          <b>{{ $t('dashboard.nextShoots') }}</b>
          <span class="n">{{ $t('dashboard.inProduction', { count: production.length }) }}</span>
        </div>

        <!-- The day's lead shoot leads the block as an object with readiness
             marks, every one of them a real fact. -->
        <Link
          v-if="nextShoot"
          class="nextshoot"
          :href="`/auftraege/${nextShoot.projectId}/shootings`"
        >
          <span class="top">
            <b>{{ nextShoot.title }} · {{ nextShoot.projectName }}</b>
            <span class="chip now" style="margin-left: auto">{{ shortDate(nextShoot.date) }}</span>
          </span>
          <span class="sub">
            {{
              [
                nextShoot.callTime && `Antritt ${nextShoot.callTime}`,
                nextShoot.venue,
                nextShoot.crew,
              ]
                .filter(Boolean)
                .join(' · ') || $t('dashboard.noDetails')
            }}
          </span>
          <span v-if="nextShoot.readiness.length" class="ready">
            <i v-for="mark in nextShoot.readiness" :key="mark.label" :class="{ no: !mark.done }">
              {{ mark.done ? '✓' : '✗' }} {{ mark.label }}
            </i>
          </span>
        </Link>

        <!-- Every row is something the studio owes and has not done. Chips
             state, buttons act: the row opens the project, the button performs
             the one named action. -->
        <div v-for="task in worklist" :key="task.id" class="dr">
          <Link class="who" :href="`/auftraege/${task.projectId}`">{{ task.projectName }}</Link>
          <span class="what">
            {{ task.title }}
            <i v-if="task.isOverdue"> · {{ $t('dashboard.overdue', { date: shortDate(task.dueOn) }) }}</i>
            <template v-else-if="task.dueOn"> · {{ shortDate(task.dueOn) }}</template>
          </span>
          <Button
            type="button"
            variant="outline"
            :tone="task.isOverdue ? 'accent' : 'default'"
            @click="complete(task)"
          >
            {{ $t('dashboard.done') }}
          </Button>
        </div>

        <!-- The production board itself: one row per project in production,
             carrying the shoot that speaks for it. -->
        <div v-if="production.length" class="tlist" style="margin-top: var(--s4)">
          <Link
            v-for="row in production"
            :key="row.projectId"
            class="dr"
            :href="`/auftraege/${row.projectId}/shootings`"
          >
            <span class="who">{{ row.projectName }}</span>
            <span class="what">
              {{ row.shootLabel ?? row.kind }}
              <template v-if="row.date"> · {{ shortDate(row.date) }}</template>
              <template v-else> · {{ $t('board.openDate') }}</template>
              <template v-if="row.crewCount"> · {{ $t('dashboard.crewCount', row.crewCount) }}</template>
            </span>
            <span class="chip next">{{ row.statusLabel }}</span>
          </Link>
        </div>

        <p v-if="!nextShoot && !production.length && !worklist.length" class="hint">
          {{ $t('dashboard.nothingDue') }}
        </p>

        <!-- One row per project, so the rest of what a project owes lives on
             the project screen rather than crowding the day out. -->
        <span v-if="openTasks > worklist.length" class="blkfoot">
          {{ $t('dashboard.moreTasks', { count: openTasks }) }}
        </span>
      </div>

      <!-- No footer: unlike inquiries, this column has no "all payments"
           board to link to yet. -->
      <DomainColumn
        v-if="payments.length"
        :title="$t('dashboard.payments')"
        :count="$t('dashboard.items', { count: payments.length })"
        :rows="payments"
      />

      <!-- No footer: the mockup's review-request note describes an
           automation nothing in this codebase runs, and stating it here
           would be inventing a fact this screen's design forbids. -->
      <DomainColumn
        v-if="afterDelivery.length"
        :title="$t('dashboard.afterDelivery')"
        :count="$t('dashboard.occasions', { count: afterDelivery.length })"
        :rows="afterDelivery"
      />
    </div>
  </AdminLayout>
</template>
