<script setup lang="ts">
import { computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

type Figure = { value: string | null; proof: string | null }

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  shoots: App.Crm.Data.ShootData[]
  paymentPlan: App.Payments.Data.PaymentPlanData | null
  notes: App.Crm.Data.InternalNoteData[]
  tasks: App.Crm.Data.TaskRowData[]
  shootOptions: { value: string; label: string }[]
  activity: { event: string; label: string; at: string; actorName: string | null }[]
  figures: Record<string, Figure>
  scope: {
    items: {
      id: string
      title: string
      quantity: number
      unitPrice: number | null
      state: string | null
      wasUnitPrice: number | null
    }[]
    delta: number
    hasDraft: boolean
  }
  people: { id: string; name: string; role: string; email: string | null; phone: string | null }[]
  galleries: { id: string; title: string | null; expiresAt: string | null }[]
}>()

const money = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' }) : '—'
}

function euro(minor: string | number | null) {
  return minor === null ? '—' : money.format(Number(minor) / 100)
}

// C-11 (#247): the same build in every phase. Before the first shoot the
// history is short and the open list long, after delivery the other way
// round, three years later "Offen" holds one row — the follow-up — and the
// trace holds the repeat orders instead of dates. Building a head per phase
// means widening the screen with every phase that turns up.
const open = computed(() => props.tasks.filter((task) => !task.isDone))
const done = computed(() => props.tasks.filter((task) => task.isDone))

const note = useForm({ body: '' })

function addNote() {
  note.post(`/auftraege/${props.header.id}/notizen`, {
    preserveScroll: true,
    onSuccess: () => note.reset(),
  })
}

function complete(task: App.Crm.Data.TaskRowData) {
  router.post(`/aufgaben/${task.id}/erledigt`, {}, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="projekte">
    <div data-theme="crm" style="display: flex; align-items: stretch">
      <div class="work">
        <div class="crumb">
          <Link href="/projekte">{{ $t('projects.title') }}</Link>
        </div>

        <div class="title">
          <div><h1>{{ header.names }}</h1></div>
          <div class="acts">
            <Link v-if="header.canManageQuotes" class="link" :href="`/auftraege/${header.id}/angebote`">
              {{ $t('project.quotes') }}
            </Link>
            <Link class="btn" :href="`/auftraege/${header.id}/shootings`">{{ $t('project.shoots') }}</Link>
          </div>
        </div>

        <!-- Master data across, six values on one line, each with its proof
             underneath — not a phase tile that a seventh phase would break. -->
        <div class="band" style="margin-top: var(--s5)">
          <div class="cell">
            <div class="lab">{{ $t('project.position') }}</div>
            <div class="val plain">{{ figures.phase.value ?? '—' }}</div>
            <div v-if="figures.phase.proof" class="sub">{{ figures.phase.proof }}</div>
          </div>
          <div class="cell">
            <div class="lab">{{ $t('project.mainDateLabel') }}</div>
            <div class="val plain">{{ day(figures.nextDate.value) }}</div>
            <div class="sub">{{ $t('board.shootCount', shoots.length) }}</div>
          </div>
          <div class="cell">
            <div class="lab">{{ $t('board.colValue') }}</div>
            <div class="val">{{ euro(figures.value.value) }}</div>
            <div v-if="figures.value.proof" class="sub">{{ figures.value.proof }}</div>
          </div>
          <div class="cell">
            <div class="lab">{{ $t('project.openAmount') }}</div>
            <div class="val" :class="{ warn: Number(figures.open.value ?? 0) > 0 }">{{ euro(figures.open.value) }}</div>
            <div v-if="figures.open.proof" class="sub">{{ $t('project.instalments', Number(figures.open.proof)) }}</div>
          </div>
          <div class="cell">
            <div class="lab">{{ $t('project.portal') }}</div>
            <div class="val plain">
              {{ figures.portal.value === 'on' ? $t('contacts.portalOn') : $t('contacts.portalOff') }}
            </div>
          </div>
        </div>

        <section class="sec">
          <h2>{{ $t('lead.dates') }}</h2>
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
            <p v-if="!shoots.length" class="hint">{{ $t('project.noShoots') }}</p>
          </div>
        </section>

        <!-- The scope as it stands, not a copy of the quote. Nothing is
             locked: the order changes in conversation and the document lags
             behind. Forbid the change and the scope is simply wrong while the
             work happens anyway. -->
        <section v-if="scope.items.length" class="sec">
          <h2>{{ $t('project.scope') }}</h2>
          <div class="quote">
            <div v-for="item in scope.items" :key="item.id" class="qr">
              <span class="ti">
                {{ item.title }}
                <span v-if="item.state" class="tag">{{ $t(`project.scope_${item.state}`) }}</span>
              </span>
              <span class="qty">{{ item.quantity }}×</span>
              <span class="amt">
                {{ item.unitPrice === null ? $t('packages.included') : euro(item.unitPrice) }}
                <span v-if="item.state === 'changed'" class="was">{{ euro(item.wasUnitPrice) }}</span>
              </span>
            </div>
          </div>

          <p v-if="scope.delta" class="meta">
            <em>{{ $t('project.scopeDelta', { amount: euro(Math.abs(scope.delta)) }) }}</em>
            <Link class="link" :href="`/auftraege/${header.id}/angebote`" style="margin-left: var(--s3)">
              {{ $t('project.sendAddendum') }}
            </Link>
          </p>
        </section>

        <section class="sec">
          <h2>{{ $t('project.openItems') }}</h2>
          <div class="todo">
            <div v-for="task in open" :key="task.id" class="r">
              <button class="b" type="button" @click="complete(task)" />
              <span class="t">{{ task.title }}</span>
              <span class="aud">{{ task.audience }}</span>
              <span class="due" :class="{ warn: task.isOverdue }">{{ task.dueOn ? day(task.dueOn) : '' }}</span>
            </div>
            <p v-if="!open.length" class="hint">{{ $t('dashboard.nothingDue') }}</p>
          </div>
        </section>

        <section class="sec">
          <h2>{{ $t('lead.notes') }}</h2>

          <form class="composer form" @submit.prevent="addNote">
            <textarea v-model="note.body" rows="2" :placeholder="$t('lead.notePlaceholder')" />
            <div class="foot">
              <button class="btn-2" type="submit" :disabled="!note.body || note.processing">
                {{ $t('lead.addNote') }}
              </button>
            </div>
          </form>

          <!-- Notes and the audit trail in one trace: what happened and what
               somebody wrote about it are read together or not at all. -->
          <div class="stream">
            <div v-for="note in notes" :key="note.id" class="ev">
              <span class="t">{{ day(note.writtenAt) }}</span>
              <span class="c">{{ note.body }} <span class="by">— {{ note.author }}</span></span>
            </div>
            <div v-for="(entry, index) in activity" :key="index" class="ev sys">
              <span class="t">{{ day(entry.at) }}</span>
              <span class="c">
                {{ entry.label }}
                <span v-if="entry.actorName" class="by"> — {{ entry.actorName }}</span>
              </span>
            </div>
            <div v-for="task in done" :key="task.id" class="ev sys">
              <span class="t">{{ task.dueOn ? day(task.dueOn) : '' }}</span>
              <span class="c">{{ task.title }}</span>
            </div>
          </div>
        </section>
      </div>

      <aside class="rail">
        <h2>{{ $t('lead.people') }}</h2>
        <div v-for="person in people" :key="person.id" class="p">
          <Link class="n" :href="`/kontakte/${person.id}`">{{ person.name }}</Link>
          <span class="st">{{ $t(`contacts.role.${person.role}`) }}</span>
        </div>

        <template v-if="paymentPlan">
          <h2>{{ $t('dashboard.payments') }}</h2>
          <div v-for="instalment in paymentPlan.installments" :key="instalment.id" class="k">
            <span>{{ day(instalment.dueOn) }}</span>
            <span>{{ euro(instalment.amount) }}</span>
          </div>
        </template>

        <template v-if="galleries.length">
          <h2>{{ $t('contacts.galleries') }}</h2>
          <div v-for="gallery in galleries" :key="gallery.id" class="item">
            <Link class="n" :href="`/galerien/${gallery.id}/medien`">{{ gallery.title ?? '—' }}</Link>
            <div v-if="gallery.expiresAt" class="m">{{ $t('contacts.expiresOn', { date: day(gallery.expiresAt) }) }}</div>
          </div>
        </template>

        <h2>{{ $t('project.documents') }}</h2>
        <div class="item">
          <Link class="n" :href="`/auftraege/${header.id}/vertrag`">{{ $t('project.contract') }}</Link>
        </div>
        <div class="item">
          <Link class="n" :href="`/auftraege/${header.id}/frageboegen`">{{ $t('project.forms') }}</Link>
        </div>
        <div class="item">
          <Link class="n" :href="`/auftraege/${header.id}/nachrichten`">{{ $t('project.messages') }}</Link>
        </div>
      </aside>
    </div>
  </AdminLayout>
</template>
