<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

type QueueRow = { id: string; name: string; date: string | null; open: boolean; waitingDays: number }

const props = defineProps<{
  queue: { key: string; rows: QueueRow[] }[]
  lead: {
    id: string
    projectId: string
    name: string
    type: string
    source: string
    stage: string
    stageLabel: string
    inquiryText: string | null
    capturedVia: string | null
    capturedAt: string
    booked: boolean
  }
  people: { id: string; name: string; role: string; email: string | null; phone: string | null }[]
  shoots: App.Crm.Data.ShootData[]
  quote: App.Quotes.Data.QuoteData | null
  packages: { id: string; name: string; itemCount: number; total: number }[]
  tasks: App.Crm.Data.TaskRowData[]
  notes: App.Crm.Data.InternalNoteData[]
  week: { date: string; what: string }[]
  conflicts: App.Crm.Data.DateConflictData[]
}>()

const money = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' }) : '—'
}

// ↑↓ walks the queue without leaving the screen. Response time is the lever:
// eight days of silence happen when every step costs a page load.
const flat = computed(() => props.queue.flatMap((group) => group.rows))

function step(by: number) {
  const at = flat.value.findIndex((row) => row.open)
  const next = flat.value[at + by]

  if (next) {
    router.get(`/anfragen/${next.id}`, {}, { preserveScroll: true, preserveState: true })
  }
}

function onKey(event: KeyboardEvent) {
  if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
    return
  }

  // Not every keydown target is an element — one dispatched on the window
  // has no matches() — and an arrow key inside a field belongs to the field.
  const target = event.target as Element | null

  if (typeof target?.matches === 'function' && target.matches('input, textarea, select')) {
    return
  }

  event.preventDefault()
  step(event.key === 'ArrowDown' ? 1 : -1)
}

onMounted(() => addEventListener('keydown', onKey))
onUnmounted(() => removeEventListener('keydown', onKey))

// Exactly one filled button, and it moves with the action that is due.
const due = computed(() => {
  if (props.quote === null) {
    return { label: 'lead.writeQuote', href: `/auftraege/${props.lead.projectId}/angebote` }
  }

  return props.lead.stage === 'quote_accepted'
    ? { label: 'lead.sendContract', href: `/auftraege/${props.lead.projectId}/vertrag` }
    : { label: 'lead.openProject', href: `/auftraege/${props.lead.projectId}` }
})
</script>

<template>
  <AdminLayout active="anfragen">
    <div data-theme="crm" style="display: flex; align-items: stretch; min-height: 80vh">
      <!-- Grouped by working state, not by the six funnel stages: six stages
           are the view of the funnel, three groups the view of the day. -->
      <nav class="queue">
        <template v-for="group in queue" :key="group.key">
          <h2 v-if="group.rows.length">
            <b>{{ $t(`lead.group.${group.key}`) }}</b>
            <span class="n">{{ group.rows.length }}</span>
          </h2>

          <Link v-for="row in group.rows" :key="row.id" :class="{ on: row.open }" :href="`/anfragen/${row.id}`">
            <span class="nm">{{ row.name }}</span>
            <span class="mt" :class="{ warn: row.waitingDays >= 3 }">
              {{ row.date ? day(row.date) : $t('board.openDate') }}
              <template v-if="row.waitingDays >= 3"> · {{ $t('lead.silentFor', row.waitingDays) }}</template>
            </span>
          </Link>
        </template>
      </nav>

      <main class="detail">
        <!-- Opened from the palette, the phase is the one word that is
             missing, so the breadcrumb carries it. -->
        <div class="crumb">
          <Link href="/anfragen">{{ $t('board.title') }}</Link> · {{ lead.stageLabel }}
          <kbd>↑</kbd><kbd>↓</kbd>
        </div>

        <!-- The head carries identity. No phase line: it stood in full twenty
             pixels further down anyway. -->
        <div class="title">
          <div><h1>{{ lead.name }}</h1></div>
          <div class="acts">
            <Link class="btn" :href="due.href">{{ $t(due.label) }}</Link>
          </div>
        </div>

        <section>
          <header><h2>{{ $t('lead.said') }}</h2></header>
          <div class="said">
            <p v-if="lead.inquiryText">{{ lead.inquiryText }}</p>
            <p v-else class="v none">{{ $t('lead.noText') }}</p>

            <div class="who">
              <!-- Occasion and origin at the top of the same column, not as a
                   subline on the title: they describe the case, not the
                   screen. -->
              <div class="via top"><b>{{ lead.type }}</b>{{ lead.source }}</div>

              <div v-for="person in people" :key="person.id" class="p">
                <div class="nm">{{ person.name }}</div>
                <div class="ro">{{ $t(`contacts.role.${person.role}`) }}</div>
                <div v-if="person.email" class="v"><a class="link" :href="`mailto:${person.email}`">{{ person.email }}</a></div>
                <div v-if="person.phone" class="v"><a class="link" :href="`tel:${person.phone}`">{{ person.phone }}</a></div>
                <div v-if="!person.email && !person.phone" class="v none">{{ $t('lead.noReach') }}</div>
              </div>

              <div class="via">
                <b>{{ lead.capturedVia ?? lead.source }}</b>
                {{ new Date(lead.capturedAt).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) }}
              </div>
            </div>
          </div>
        </section>

        <section v-if="shoots.length">
          <header><h2>{{ $t('lead.dates') }}</h2><span class="n">{{ shoots.length }}</span></header>
          <div class="rowset">
            <div v-for="shoot in shoots" :key="shoot.id" class="r">
              <span class="when">
                {{ day(shoot.date) }}
                <span class="st">{{ shoot.statusLabel }}</span>
              </span>
              <span class="what">
                {{ shoot.label ?? shoot.kind }}
                <span v-if="shoot.venue" class="s"> · {{ shoot.venue }}</span>
              </span>
              <span class="right">{{ shoot.crew.map((member) => member.name).join(', ') || '—' }}</span>
            </div>
          </div>
        </section>

        <section>
          <header><h2>{{ $t('lead.quote') }}</h2></header>

          <div v-if="quote" class="quote">
            <div v-for="item in quote.items" :key="item.id" class="qr">
              <span class="ti">{{ item.title }}</span>
              <span class="qty">{{ item.quantity }}×</span>
              <span class="amt">{{ item.unitPrice === null ? '—' : money.format(item.unitPrice / 100) }}</span>
            </div>
            <div class="sum">
              <span class="ti">{{ quote.statusLabel }}</span>
              <span class="amt">{{ money.format(quote.total / 100) }}</span>
            </div>
            <p v-if="quote.validUntil" class="meta">{{ $t('lead.validUntil', { date: day(quote.validUntil) }) }}</p>
          </div>

          <!-- No quote yet: the active packages, because the next move is
               choosing one. An empty section would state the same thing and
               offer nothing. -->
          <div v-else class="ghosts">
            <Link
              v-for="pkg in packages"
              :key="pkg.id"
              class="ghost"
              :href="`/auftraege/${lead.projectId}/angebote`"
            >
              <b>{{ pkg.name }}</b> {{ $t('packages.itemCount', pkg.itemCount) }} · {{ money.format(pkg.total / 100) }}
            </Link>
          </div>
        </section>

        <section v-if="tasks.length">
          <header><h2>{{ $t('lead.tasks') }}</h2><span class="n">{{ tasks.length }}</span></header>
          <div class="todo">
            <div v-for="task in tasks" :key="task.id" class="r" :class="{ done: task.isDone }">
              <span class="b" :class="{ done: task.isDone }" />
              <span class="t">{{ task.title }}</span>
              <span class="aud">{{ task.audience }}</span>
              <span class="due" :class="{ warn: task.isOverdue }">{{ task.dueOn ? day(task.dueOn) : '' }}</span>
            </div>
          </div>
        </section>

        <section v-if="notes.length">
          <header><h2>{{ $t('lead.notes') }}</h2></header>
          <div class="log">
            <div v-for="note in notes" :key="note.id" class="e">
              <span class="t">{{ day(note.writtenAt) }}</span>
              <span class="c">{{ note.body }} <span class="by">— {{ note.author }}</span></span>
            </div>
          </div>
        </section>
      </main>

      <!-- The trace: what lies around the requested day. A date is answered
           by what is already on it. -->
      <aside class="rail">
        <h2>{{ $t('lead.week') }}</h2>
        <div v-if="week.length">
          <div v-for="entry in week" :key="`${entry.date}${entry.what}`" class="d">
            <span class="dd">{{ day(entry.date) }}</span>
            <span class="ev">{{ entry.what }}</span>
          </div>
        </div>
        <div v-else class="d free">
          <span class="ev">{{ $t('lead.weekFree') }}</span>
        </div>

        <template v-if="conflicts.length">
          <h2>{{ $t('lead.conflicts') }}</h2>
          <div v-for="(conflict, index) in conflicts" :key="index" class="d set">
            <span class="ev">{{ conflict.label ?? conflict.projectName }}</span>
          </div>
        </template>
      </aside>
    </div>
  </AdminLayout>
</template>
