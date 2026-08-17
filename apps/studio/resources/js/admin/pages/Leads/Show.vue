<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

type QueueRow = { id: string; name: string; date: string | null; open: boolean; waitingDays: number }

const props = defineProps<{
  queue: { key: string; rows: QueueRow[] }[]
  lead: {
    id: string
    projectId: string
    name: string
    type: string
    typeLabel: string
    source: string
    sourceLabel: string
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
  types: { value: string; label: string }[]
  sources: { value: string; label: string }[]
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

// C-10 (#245): editing is a state of this view — no second screen, no route
// of its own. Nothing jumps and nothing moves: the same fields become
// operable where they already stand.
//
// One save for the whole state rather than one per section, and the head is
// sticky so it does not scroll away on a long case — which is the objection
// the draft's single button invites.
const editing = ref(false)

const edit = useForm({
  type: props.lead.type,
  source: props.lead.source,
  inquiry_text: props.lead.inquiryText ?? '',
  people: props.people.map((person) => ({
    id: person.id,
    name: person.name,
    email: person.email ?? '',
    phone: person.phone ?? '',
  })),
  // From booked, date and place are contract content and do not travel at
  // all — see the controller.
  shoots: props.lead.booked ? [] : props.shoots.map((shoot) => ({
    id: shoot.id,
    date: shoot.date ?? '',
    venue_name: shoot.venue?.split(', ')[0] ?? '',
    venue_city: shoot.venue?.split(', ')[1] ?? '',
    call_time: shoot.callTime ?? '',
    end_time: shoot.endTime ?? '',
  })),
})

function save() {
  edit.transform((data) => ({
    ...data,
    inquiry_text: data.inquiry_text === '' ? null : data.inquiry_text,
    people: data.people.map((person) => ({
      ...person,
      email: person.email === '' ? null : person.email,
      phone: person.phone === '' ? null : person.phone,
    })),
    shoots: data.shoots.map((shoot) => ({
      ...shoot,
      date: shoot.date === '' ? null : shoot.date,
      call_time: shoot.call_time === '' ? null : shoot.call_time,
      end_time: shoot.end_time === '' ? null : shoot.end_time,
    })),
  })).patch(`/anfragen/${props.lead.id}`, {
    preserveScroll: true,
    onSuccess: () => (editing.value = false),
  })
}

// Created in the section they belong to. No dialog: a window takes away the
// context one reads while writing — the last three notes, the other dates.
const note = useForm({ body: '' })
const task = useForm({ title: '', audience: 'internal', due_on: '' })

function addNote() {
  note.post(`/auftraege/${props.lead.projectId}/notizen`, {
    preserveScroll: true,
    onSuccess: () => note.reset(),
  })
}

function addTask() {
  task.post(`/auftraege/${props.lead.projectId}/aufgaben`, {
    preserveScroll: true,
    onSuccess: () => task.reset(),
  })
}

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
        <div class="title" style="position: sticky; top: 0; z-index: 5; background: var(--paper)">
          <div><h1>{{ lead.name }}</h1></div>

          <div v-if="editing" class="acts">
            <a class="link" href="#" @click.prevent="editing = false">{{ $t('common.cancel') }}</a>
            <button class="btn" type="button" :disabled="edit.processing" @click="save">
              {{ $t('common.save') }}
            </button>
          </div>

          <div v-else class="acts">
            <a class="link" href="#" @click.prevent="editing = true">{{ $t('lead.edit') }}</a>
            <Link class="btn" :href="due.href">{{ $t(due.label) }}</Link>
          </div>
        </div>

        <section>
          <header><h2>{{ $t('lead.said') }}</h2></header>
          <div class="said">
            <div v-if="editing" class="form" style="flex: 1; min-width: 0; max-width: 58ch">
              <textarea v-model="edit.inquiry_text" rows="5" />
            </div>
            <p v-else-if="lead.inquiryText">{{ lead.inquiryText }}</p>
            <p v-else class="v none">{{ $t('lead.noText') }}</p>

            <div class="who">
              <!-- Occasion and origin at the top of the same column, not as a
                   subline on the title: they describe the case, not the
                   screen. -->
              <div v-if="editing" class="via top form">
                <div class="choice" style="margin-bottom: 8px">
                  <label v-for="option in types" :key="option.value">
                    <input v-model="edit.type" type="radio" :value="option.value" />
                    <span>{{ option.label }}</span>
                  </label>
                </div>
                <div class="choice">
                  <label v-for="option in sources" :key="option.value">
                    <input v-model="edit.source" type="radio" :value="option.value" />
                    <span>{{ option.label }}</span>
                  </label>
                </div>
              </div>
              <div v-else class="via top"><b>{{ lead.typeLabel }}</b>{{ lead.sourceLabel }}</div>

              <div v-if="editing" class="form">
                <div v-for="person in edit.people" :key="person.id" class="p">
                  <input v-model="person.name" type="text" style="margin-bottom: 6px" />
                  <input v-model="person.email" type="email" inputmode="email" style="margin-bottom: 6px" />
                  <input v-model="person.phone" type="tel" inputmode="tel" />
                </div>
              </div>

              <div v-for="person in editing ? [] : people" :key="person.id" class="p">
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
          <!-- From booked the date and the place are contract content: they
               leave the edit state rather than standing there locked with a
               reason, and the read view keeps them. -->
          <div v-if="editing && !lead.booked" class="form">
            <div v-for="shoot in edit.shoots" :key="shoot.id" class="shootblock">
              <div class="line">
                <div class="field"><span>{{ $t('project.shootDate') }}</span><input v-model="shoot.date" type="date" /></div>
                <div class="field"><span>{{ $t('project.callTime') }}</span><input v-model="shoot.call_time" type="time" /></div>
                <div class="field"><span>{{ $t('project.endTime') }}</span><input v-model="shoot.end_time" type="time" /></div>
              </div>
              <div class="line">
                <div class="field grow"><span>{{ $t('project.venueName') }}</span><input v-model="shoot.venue_name" type="text" /></div>
                <div class="field grow"><span>{{ $t('project.venueCity') }}</span><input v-model="shoot.venue_city" type="text" /></div>
              </div>
            </div>
          </div>

          <div v-else class="rowset">
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

        <section>
          <header><h2>{{ $t('lead.tasks') }}</h2><span class="n">{{ tasks.length }}</span></header>

          <!-- One row: title, who it is for, when it is due. -->
          <form class="composer form" @submit.prevent="addTask">
            <div class="line">
              <div class="field grow">
                <span>{{ $t('lead.taskTitle') }}</span>
                <input v-model="task.title" type="text" />
              </div>
              <div class="field">
                <span>{{ $t('lead.taskAudience') }}</span>
                <select v-model="task.audience">
                  <option value="internal">{{ $t('lead.audienceInternal') }}</option>
                  <option value="client">{{ $t('lead.audienceClient') }}</option>
                </select>
              </div>
              <div class="field">
                <span>{{ $t('lead.taskDue') }}</span>
                <input v-model="task.due_on" type="date" />
              </div>
            </div>
            <div class="foot">
              <button class="btn-2" type="submit" :disabled="!task.title || task.processing">
                {{ $t('lead.addTask') }}
              </button>
            </div>
          </form>
          <div class="todo">
            <div v-for="task in tasks" :key="task.id" class="r" :class="{ done: task.isDone }">
              <span class="b" :class="{ done: task.isDone }" />
              <span class="t">{{ task.title }}</span>
              <span class="aud">{{ task.audience }}</span>
              <span class="due" :class="{ warn: task.isOverdue }">{{ task.dueOn ? day(task.dueOn) : '' }}</span>
            </div>
          </div>
        </section>

        <section>
          <header><h2>{{ $t('lead.notes') }}</h2></header>

          <!-- Above the log, so the last notes stay visible: that context is
               exactly what a window would take away. -->
          <form class="composer form" @submit.prevent="addNote">
            <textarea v-model="note.body" rows="2" :placeholder="$t('lead.notePlaceholder')" />
            <div class="foot">
              <button class="btn-2" type="submit" :disabled="!note.body || note.processing">
                {{ $t('lead.addNote') }}
              </button>
            </div>
          </form>
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
