<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Link } from '@inertiajs/vue3'
import PortalChip from '@/portal/components/PortalChip.vue'
import PortalDot from '@/portal/components/PortalDot.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalOffers from '@/portal/components/PortalOffers.vue'
import PortalSuggestion from '@/portal/components/PortalSuggestion.vue'
import PortalPerson from '@/portal/components/PortalPerson.vue'
import PortalRow from '@/portal/components/PortalRow.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { dayDate, daysUntil } from '@/portal/lib/dates'
import { reachable, type Studio } from '@/portal/lib/studio'
import { Button } from '@/ui/button'
import { formatEuros } from '@/lib/money'

type Shoot = {
  id: string
  title: string
  date: string | null
  callTime: string | null
  venue: string | null
  phase: 'upcoming' | 'editing' | 'delivered'
}

type Document = {
  kind: 'contract' | 'quote' | 'messages'
  href: string
  dueOn: string | null
  signedOn?: string | null
  acceptedOn?: string | null
  amount: number | null
  unread?: number
  settled: boolean
}

type Step = {
  kind: 'contract' | 'quote' | 'installment' | 'questionnaire'
  label: string | null
  detail: string | null
  amount: number | null
  dueOn: string | null
  overdue: boolean
  href: string
}

type Offers = {
  suggestion: { service: App.Catalog.Data.PortalServiceData; note: string | null; author: string | null } | null
  shelf: App.Catalog.Data.PortalServiceData[]
}

const { t } = useI18n()

const props = defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  studio: Studio
  project: {
    id: string
    name: string
    type: string
    date: string | null
    venue: string | null
    shoots: Shoot[]
    gallery: { id: string; title: string | null; count: number; expiresAt: string | null; strip: string[] } | null
    documents: Document[]
    payments: { openTotal: number; rows: App.Payments.Data.PaymentInstallmentData[] } | null
    steps: Step[]
    appointment: { title: string; date: string; callTime: string | null; venue: string | null } | null
    questionnaires: { id: string; name: string; count: number; openCount: number }[]
    details: { date: string | null; callTime: string | null; venue: string | null; email: string | null; phone: string | null }
  }
  offers: Offers
}>()

const STEP_BUTTON: Record<Step['kind'], string> = {
  contract: 'portal.signButton',
  quote: 'portal.quoteAccept',
  installment: 'portal.payButton',
  questionnaire: 'portal.answerButton',
}

const steps = computed(() => props.project.steps.map(step => ({
  ...step,
  title: step.kind === 'installment'
    ? (step.label ?? t('portal.payment'))
    : step.kind === 'questionnaire' ? t('portal.questionnairesTitle') : t(`portal.document.${step.kind}`),
  note: step.dueOn
    ? { key: step.overdue ? 'portal.overdueSince' : step.kind === 'quote' ? 'portal.validUntil' : 'portal.dueOn', date: step.dueOn }
    : step.kind === 'contract' ? { key: 'portal.notSignedYet', date: null } : null,
  state: (step.overdue ? 'late' : step.dueOn ? 'due' : 'open') as 'late' | 'due' | 'open',
  label: STEP_BUTTON[step.kind],
})))

const shoots = computed(() => [...props.project.shoots].sort((a, b) => (a.date ?? '9999').localeCompare(b.date ?? '9999')))

const SHOOT_DOT = { upcoming: 'neutral', editing: 'open', delivered: 'done' } as const

const delivered = (shoot: Shoot) => shoot.phase === 'delivered' && props.project.gallery !== null

const galleryExpiring = computed(() => {
  const expiresAt = props.project.gallery?.expiresAt

  if (!expiresAt) return null
  const days = daysUntil(expiresAt)

  return days >= 0 && days <= 14 ? days : null
})

const step = computed(() => steps.value[0] ?? null)

// What stands in the card and the list above is not listed twice.
const documents = computed(() => props.project.documents.filter(document => !steps.value.some(one => one.kind === document.kind)))

const sheets = computed(() => props.project.questionnaires.filter(sheet => sheet.openCount === 0))

const messageHref = computed(() => `/nachrichten?betrifft=${props.project.id}`)

const BAND = 'mt-10 border-t border-border pt-8'

function paymentState(row: App.Payments.Data.PaymentInstallmentData) {
  if (row.status === 'cancelled') return 'neutral'
  if (row.status === 'paid') return 'done'

  return row.isLate ? 'late' : daysUntil(row.dueOn) <= 14 ? 'due' : 'open'
}

function paymentNote(row: App.Payments.Data.PaymentInstallmentData) {
  const state = row.status === 'paid'
    ? row.statusLabel
    : row.status === 'cancelled' ? null : t(row.isLate ? 'portal.overdueSince' : 'portal.dueOn', { date: dayDate(row.dueOn) })

  return [state, row.invoiceNumber].filter(Boolean).join(' · ') || null
}

function documentNote(document: Document) {
  if (document.kind === 'contract') {
    return document.signedOn ? 'signed' : 'unsigned'
  }

  if (document.kind === 'quote') {
    return document.acceptedOn ? 'accepted' : 'open'
  }

  return 'messages'
}
</script>

<template>
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: showSwitcher }" />

    <PortalWrap class="pb-[calc(80px+env(safe-area-inset-bottom))]">
      <section class="pt-6">
        <p class="animate-rise motion-reduce:animate-none mb-3 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.type }}</p>
        <h1 class="animate-rise motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ project.name }}
        </h1>
        <p class="mt-1.5 text-ui leading-[1.5] tabular-nums text-muted-foreground">
          {{ [project.date ? dayDate(project.date) : $t('portal.dateOpen'), project.venue].filter(Boolean).join(' · ') }}
        </p>
      </section>

      <section v-if="step" class="-mx-2 mt-6 rounded-md border border-line-hover p-6">
        <PortalLabel>{{ $t('portal.nextStep') }}</PortalLabel>
        <div class="mt-3 flex items-baseline justify-between gap-4">
          <h2 class="font-serif text-[22px] leading-[1.25] font-normal tracking-[-0.01em]">{{ step.title }}</h2>
          <span v-if="step.amount" class="text-[17px] leading-none font-semibold tabular-nums">{{ formatEuros(step.amount) }}</span>
        </div>
        <p v-if="step.note || step.detail" class="mt-1.5 flex items-center gap-2 text-[13.5px] text-muted-foreground">
          <PortalDot :state="step.state" />
          {{ step.detail ?? $t(step.note!.key, { date: step.note?.date ? dayDate(step.note.date) : '' }) }}
        </p>
        <Button as-child variant="portal" class="mt-5 min-h-12 w-full justify-center text-[15px]">
          <Link :href="step.href">{{ $t(step.label) }}</Link>
        </Button>
      </section>

      <section v-else-if="project.appointment" class="-mx-2 mt-6 rounded-md border border-line-hover p-6">
        <PortalLabel>{{ $t('portal.appointment') }}</PortalLabel>
        <h2 class="mt-3 font-serif text-[22px] leading-[1.25] font-normal tracking-[-0.01em] tabular-nums">
          {{ [dayDate(project.appointment.date), project.appointment.callTime].filter(Boolean).join(' · ') }}
        </h2>
        <p class="mt-1.5 text-[13.5px] text-muted-foreground">
          {{ [project.appointment.title, project.appointment.venue].filter(Boolean).join(' · ') }}
        </p>
      </section>

      <section v-if="steps.length > 1" class="mt-8">
        <PortalLabel class="mb-2">{{ $t('portal.openTitle') }}</PortalLabel>
        <PortalRow
          v-for="other in steps.slice(1)"
          :key="other.href"
          :href="other.href"
          :title="other.title"
          :state="other.state"
          :note="other.detail ?? (other.note ? $t(other.note.key, { date: other.note.date ? dayDate(other.note.date) : '' }) : null)"
          :aside="other.amount ? formatEuros(other.amount) : null"
          aside-open
        />
      </section>

      <section v-if="project.gallery" :class="BAND">
        <div class="mb-6 flex items-baseline justify-between gap-4">
          <PortalLabel>{{ $t('portal.imagesTitle') }}</PortalLabel>
          <a class="text-[13.5px] text-action" :href="`/galerien/${project.gallery.id}`">{{ $t('portal.viewAll') }}</a>
        </div>
        <div class="mb-6 grid grid-cols-[1.5fr_1fr_1fr_1.3fr] gap-0.5 max-[820px]:grid-cols-2">
          <a
            v-for="src in project.gallery.strip"
            :key="src"
            class="block overflow-hidden bg-sunk"
            :href="`/galerien/${project.gallery.id}`"
          >
            <img
              class="h-[290px] w-full object-cover max-[820px]:h-[170px]"
              :src="`${src}?w=800&fmt=jpeg`"
              alt=""
              loading="lazy"
            />
          </a>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 class="font-serif text-[22px] leading-[1.25] font-normal tracking-[-0.01em]">{{ project.gallery.title }}</h2>
            <p class="mt-1.5 text-[13.5px] tabular-nums text-muted-foreground">
              {{ $t('portal.imageCount', project.gallery.count) }}
              <template v-if="project.gallery.expiresAt && galleryExpiring === null">
                · {{ $t('gallery.onlineUntil', { date: dayDate(project.gallery.expiresAt) }) }}
              </template>
            </p>
            <p v-if="galleryExpiring !== null" class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[13.5px] tabular-nums text-muted-foreground">
              {{ $t('gallery.onlineUntil', { date: dayDate(project.gallery.expiresAt!) }) }}
              <PortalChip :tone="galleryExpiring <= 3 ? 'danger' : 'warn'">{{ $t('gallery.onlineDays', galleryExpiring) }}</PortalChip>
            </p>
          </div>
          <Button as="a" variant="portal-ghost" class="max-[820px]:w-full max-[820px]:justify-center" :href="`/galerien/${project.gallery.id}`">
            {{ $t('portal.viewAllImages', { count: project.gallery.count }) }}
            <svg viewBox="0 0 24 24" class="fill-none stroke-current"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </Button>
        </div>
      </section>

      <!-- State in the row, not behind a click. -->
      <section v-if="documents.length" :class="BAND">
        <PortalLabel class="mb-2">{{ $t('portal.documentsTitle') }}</PortalLabel>
        <PortalRow
          v-for="document in documents"
          :key="document.kind"
          :href="document.href"
          :title="$t(`portal.document.${document.kind}`)"
          :state="document.kind === 'messages' ? (document.unread ? 'open' : 'neutral') : document.settled ? 'done' : 'open'"
          :note="{
            signed: document.signedOn ? $t('portal.signedOn', { date: dayDate(document.signedOn) }) : null,
            unsigned: $t('portal.notSignedYet'),
            accepted: document.acceptedOn ? $t('portal.quoteAccepted', { date: dayDate(document.acceptedOn) }) : null,
            open: $t('portal.quoteOpen'),
            messages: document.unread ? $t('portal.unreadCount', { count: document.unread }) : null,
          }[documentNote(document)]"
          :aside="document.amount ? formatEuros(document.amount) : null"
          :aside-open="!document.settled"
        />
      </section>

      <section v-if="shoots.length" :class="BAND">
        <PortalLabel>{{ $t('portal.shootsTitle') }}</PortalLabel>
        <component
          :is="delivered(shoot) ? 'a' : 'div'"
          v-for="shoot in shoots"
          :key="shoot.id"
          :href="delivered(shoot) ? `/galerien/${project.gallery!.id}` : undefined"
          class="group grid grid-cols-[132px_1fr_auto] items-baseline gap-6 border-b border-border py-5 text-inherit no-underline last:border-b-0 max-[820px]:grid-cols-1 max-[820px]:gap-1.5"
        >
          <b class="text-[15px] leading-[1.45] font-semibold tabular-nums">
            {{ shoot.date ? [dayDate(shoot.date), shoot.callTime].filter(Boolean).join(' · ') : $t('portal.dateOpen') }}
          </b>
          <div>
            <div class="font-serif text-[19px] leading-[1.4]">{{ shoot.title }}</div>
            <div v-if="shoot.venue" class="mt-[3px] text-[13.5px] text-muted-foreground">{{ shoot.venue }}</div>
          </div>
          <div class="inline-flex items-center gap-2 text-[12.5px] whitespace-nowrap text-muted-foreground">
            <PortalDot :state="SHOOT_DOT[shoot.phase]" />{{ $t(`portal.shootPhase.${shoot.phase}`) }}
          </div>
          <span v-if="delivered(shoot)" class="col-span-full inline-flex min-h-11 items-center gap-1 text-[13.5px] text-foreground max-[820px]:justify-between">
            {{ $t('portal.viewImages') }}
            <svg class="size-[18px] fill-none stroke-current stroke-[1.4] text-mark" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
          </span>
        </component>
      </section>

      <section v-if="project.payments" :class="BAND">
        <PortalLabel class="mb-2">{{ $t('portal.paymentsTitle') }}</PortalLabel>
        <template v-for="row in project.payments.rows" :key="row.id">
          <PortalRow
            :href="row.invoiceId ? `/projekte/${project.id}/zahlungen/${row.invoiceId}` : `/projekte/${project.id}/zahlungen`"
            :title="row.label"
            :state="paymentState(row)"
            :note="paymentNote(row)"
            :aside="formatEuros(row.status === 'paid' || row.status === 'cancelled' ? row.amount : row.remaining)"
            :aside-open="row.status !== 'paid' && row.status !== 'cancelled'"
          />
          <p v-if="row.cancelledOn" class="flex items-baseline justify-between gap-4 border-b border-border py-3 pl-4 text-[13.5px] text-muted-foreground tabular-nums">
            {{ $t('portal.cancelledOn', { date: dayDate(row.cancelledOn) }) }}
          </p>
          <p
            v-for="note in row.creditNotes"
            :key="note.id"
            class="flex items-baseline justify-between gap-4 border-b border-border py-3 pl-4 text-[13.5px] text-muted-foreground tabular-nums"
          >
            <span>{{ $t('portal.creditNote', { number: note.number }) }} · {{ dayDate(note.issuedOn) }}</span>
            <span>−{{ formatEuros(note.amount) }}</span>
          </p>
        </template>
      </section>

      <!-- P-03 (#222): absent until the studio shares a questionnaire. -->
      <section v-if="sheets.length" :class="BAND">
        <PortalLabel class="mb-2">{{ $t('portal.questionnairesTitle') }}</PortalLabel>
        <PortalRow
          v-for="sheet in sheets"
          :key="sheet.id"
          :href="`/projekte/${project.id}/fragebogen/${sheet.id}`"
          :title="sheet.name"
          state="done"
          :note="$t('portal.questionsDone')"
        />
      </section>

      <!-- Date and place already stand in the header and the shoot list. -->
      <section :class="BAND">
        <PortalLabel class="mb-4">{{ $t('portal.notedTitle') }}</PortalLabel>
        <dl class="m-0 grid grid-cols-[148px_1fr] gap-x-6 gap-y-3 max-[700px]:grid-cols-1 max-[700px]:gap-y-0">
          <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.reachUs') }}</dt>
          <dd class="m-0 text-[14.5px]">
            {{ [project.details.email, project.details.phone].filter(Boolean).join(' · ') }}
            <Link class="ml-2 text-[13px] text-muted-foreground hover:text-foreground max-[700px]:inline-flex max-[700px]:min-h-11 max-[700px]:items-center" href="/einstellungen">
              {{ $t('common.edit').toLowerCase() }}
            </Link>
          </dd>
        </dl>

        <p v-if="studio.messagingEnabled" class="mt-4 flex flex-wrap items-center gap-2 text-[13px] text-muted-foreground">
          <svg class="size-3.5 flex-none fill-none stroke-current stroke-[1.5]" viewBox="0 0 24 24"><path d="M7 11V8a5 5 0 0110 0v3M5 11h14v9H5z" /></svg>
          <a class="inline-flex min-h-11 items-center text-foreground" :href="messageHref">{{ $t('portal.requestChange') }}</a>
        </p>
      </section>

      <PortalSuggestion v-if="offers.suggestion" :suggestion="offers.suggestion" class="mt-10" />

      <PortalOffers :shelf="offers.shelf" class="mt-10" />

      <section v-if="reachable(studio)" :class="BAND">
        <PortalLabel class="mb-4">{{ $t('portal.contactTitle') }}</PortalLabel>
        <PortalPerson :studio="studio" :message-href="messageHref" />
      </section>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
