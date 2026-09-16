<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
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
import { reachable, type Studio } from '@/portal/lib/studio'
import { Button } from '@/ui/button'
import { formatEuros } from '@/admin/lib/money'

type Shoot = {
  id: string
  title: string
  date: string | null
  callTime: string | null
  venue: string | null
  status: string
  state: 'open' | 'done' | 'neutral'
}

type Document = {
  kind: 'payments' | 'quote' | 'messages'
  href: string
  dueOn: string | null
  acceptedOn?: string | null
  amount: number | null
  settled: boolean
}

type Invoice = {
  id: string
  number: string
  issuedOn: string | null
  amount: number
  status: string
  settled: boolean
}

type Offers = {
  suggestion: { service: App.Catalog.Data.PortalServiceData; note: string | null; author: string | null } | null
  shelf: App.Catalog.Data.PortalServiceData[]
}

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
    invoices: Invoice[]
    questionnaires: { id: string; name: string; count: number; openCount: number }[]
    details: { date: string | null; callTime: string | null; venue: string | null; email: string | null; phone: string | null }
  }
  offers: Offers
}>()

function longDate(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  })
}

function shortDate(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function weekday(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { weekday: 'long' })
}

// One sentence instead of four tiles that all said the same thing. Counted
// in whole days from today, so "heute" and "morgen" are their own words
// rather than "in 0 Tagen".
const statement = computed(() => {
  if (props.project.date === null) {
    return null
  }

  const midnight = new Date()
  midnight.setHours(0, 0, 0, 0)
  const days = Math.round((new Date(props.project.date).getTime() - midnight.getTime()) / 86_400_000)

  if (days < 0) {
    return null
  }

  return days === 0 ? 'today' : days === 1 ? 'tomorrow' : 'countdown'
})

const daysLeft = computed(() => {
  const midnight = new Date()
  midnight.setHours(0, 0, 0, 0)

  return Math.round((new Date(props.project.date!).getTime() - midnight.getTime()) / 86_400_000)
})

const heroImage = computed(() => props.project.gallery?.strip[0] ?? null)


const messageHref = computed(() => `/nachrichten?betrifft=${props.project.id}`)

// The band the reading sections sit in. Wider than the letter and narrower
// than the page: with a date on the left and a state on the right, the full
// 1180px leaves 700px between them and the eye loses the row.
const BAND = 'max-w-[880px] border-t border-border pt-12'

function documentNote(document: Document) {
  if (document.kind === 'payments') {
    return document.settled ? 'settled' : 'due'
  }

  if (document.kind === 'quote') {
    return document.acceptedOn ? 'accepted' : 'open'
  }

  return 'messages'
}
</script>

<template>
  <PortalShell>
    <!-- The picture first. The old screen opened on the paperwork and read
         like a file. -->
    <header v-if="heroImage" class="relative h-[78vh] min-h-[640px] overflow-hidden bg-sunk max-[820px]:h-[62vh] max-[820px]:min-h-[440px]">
      <PortalHeader over :switcher="{ label: project.name, toAll: showSwitcher }" />

      <img class="size-full object-cover object-[50%_42%]" :src="`${heroImage}?w=2400&fmt=jpeg`" alt="" />

      <!-- Two gradients, not one: enough at the top to carry the bar, enough
           at the bottom to seat the serif on any photograph. A single gradient
           over the full height would wash the picture grey. -->
      <div
        class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(12,10,9,.52)_0%,rgba(12,10,9,0)_24%),linear-gradient(0deg,rgba(12,10,9,.86)_0%,rgba(12,10,9,.42)_26%,rgba(12,10,9,0)_56%)]"
      />

      <div class="absolute inset-x-0 bottom-18 z-2 mx-auto max-w-[var(--page)] px-6 text-white max-[820px]:bottom-8">
        <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-white/72">{{ project.type }}</p>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(38px,14.3px+6.08vw,86px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)] [text-shadow:0_1px_40px_rgba(0,0,0,.28)]">
          {{ project.name }}
        </h1>
        <p v-if="project.date || project.venue" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-4 text-[15px] leading-[1.5] tabular-nums text-white/86">
          {{ [project.date ? longDate(project.date) : null, project.venue].filter(Boolean).join(' · ') }}
        </p>
      </div>
    </header>

    <PortalHeader v-else :switcher="{ label: project.name, toAll: showSwitcher }" />

    <PortalWrap class="pb-40">
      <!-- No photograph yet: the head is typographic, the same way the inquiry
           is, and becomes the picture above once a shoot is delivered. -->
      <section v-if="!heroImage" class="pt-[var(--band)]">
        <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.type }}</p>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(42px,6vw,62px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ project.name }}
        </h1>
        <p v-if="project.date || project.venue" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-4 text-[15px] leading-[1.5] tabular-nums text-muted-foreground">
          {{ [project.date ? longDate(project.date) : null, project.venue].filter(Boolean).join(' · ') }}
        </p>
      </section>

      <!-- One state, one sentence. This replaced four cards that all said it. -->
      <section v-if="statement" class="pt-28 pb-[var(--band)] max-[820px]:pt-[var(--band)] max-[820px]:pb-12">
        <h2 class="max-w-[20ch] font-serif text-[clamp(28px,3.4vw,40px)] leading-[1.15] font-normal tracking-[-0.015em]">
          {{ statement === 'countdown' ? $t('portal.daysUntil', { days: daysLeft }) : $t(`portal.${statement}`) }}
        </h2>
      </section>

      <!-- The emotional core, and the one thing the old screen had no way in
           to at all. -->
      <section v-if="project.gallery" class="pb-28" :class="!statement && 'pt-[var(--band)]'">
        <div class="mb-8 grid grid-cols-[1.5fr_1fr_1fr_1.3fr] gap-3 max-[820px]:grid-cols-2">
          <a
            v-for="src in project.gallery.strip"
            :key="src"
            class="block overflow-hidden rounded-md bg-sunk"
            :href="`/galerien/${project.gallery.id}`"
          >
            <img
              class="h-[290px] w-full object-cover transition-transform duration-[1.1s] ease-[var(--ease)] hover:scale-[1.035] max-[820px]:h-[210px]"
              :src="`${src}?w=800&fmt=jpeg`"
              alt=""
              loading="lazy"
            />
          </a>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-6">
          <div>
            <h2 class="font-serif text-[24px] leading-[1.25] font-normal tracking-[-0.01em]">{{ project.gallery.title }}</h2>
            <p class="mt-1.5 text-[13.5px] tabular-nums text-muted-foreground">
              {{ $t('portal.imageCount', project.gallery.count) }}
              <template v-if="project.gallery.expiresAt">
                · {{ $t('gallery.onlineUntil', { date: shortDate(project.gallery.expiresAt) }) }}
              </template>
            </p>
          </div>
          <!-- One gallery for both audiences: the couple's own half — their
               link, its gates, their lists — sits under the pictures. -->
          <Button as="a" variant="portal" :href="`/galerien/${project.gallery.id}`">
            {{ $t('portal.viewAllImages', { count: project.gallery.count }) }}
            <svg viewBox="0 0 24 24" class="fill-none stroke-current"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </Button>
        </div>
      </section>

      <section v-if="project.shoots.length" class="pb-[var(--band)]">
        <div :class="BAND">
          <PortalLabel>{{ $t('portal.shootsTitle') }}</PortalLabel>
          <div class="mt-6">
            <div
              v-for="shoot in project.shoots"
              :key="shoot.id"
              class="grid grid-cols-[132px_1fr_auto] items-baseline gap-6 border-b border-border py-6 last:border-b-0 max-[820px]:grid-cols-1 max-[820px]:gap-2"
            >
              <div class="text-[14px] leading-[1.45] font-medium tracking-[-0.005em] tabular-nums">
                <b class="block text-[15px] font-semibold">{{ shoot.date ? shortDate(shoot.date) : $t('portal.dateOpen') }}</b>
                <template v-if="shoot.date">
                  {{ [weekday(shoot.date), shoot.callTime].filter(Boolean).join(' · ') }}
                </template>
              </div>
              <div>
                <div class="font-serif text-[19px] leading-[1.4]">{{ shoot.title }}</div>
                <div v-if="shoot.venue" class="mt-[3px] text-[13.5px] text-muted-foreground">{{ shoot.venue }}</div>
              </div>
              <div class="inline-flex items-center gap-2 text-[12.5px] whitespace-nowrap text-muted-foreground">
                <PortalDot :state="shoot.state" />{{ shoot.status }}
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- State in the row, not behind a click. Three grey text links before. -->
      <section v-if="project.documents.length">
        <div :class="BAND">
          <PortalLabel class="mb-6">{{ $t('portal.documentsTitle') }}</PortalLabel>
          <PortalRow
            v-for="document in project.documents"
            :key="document.kind"
            :href="document.href"
            :title="$t(`portal.document.${document.kind}`)"
            :state="document.settled ? 'done' : 'open'"
            :note="{
              settled: $t('portal.allSettled'),
              due: document.dueOn ? $t('portal.dueOn', { date: shortDate(document.dueOn) }) : null,
              accepted: document.acceptedOn ? $t('portal.quoteAccepted', { date: shortDate(document.acceptedOn) }) : null,
              open: $t('portal.quoteOpen'),
              messages: `${$t('portal.messagesAbout')} ${project.name}`,
            }[documentNote(document)]"
            :aside="document.amount ? formatEuros(document.amount) : null"
            :aside-open="!document.settled"
          />
        </div>
      </section>

      <!-- #415: die eigene Kopie der Rechnungsfolge, eine Zeile je
           ausgestellte Rate. -->
      <section v-if="project.invoices.length" class="pt-12">
        <div :class="BAND">
          <PortalLabel class="mb-6">{{ $t('portal.invoicesTitle') }}</PortalLabel>
          <PortalRow
            v-for="invoice in project.invoices"
            :key="invoice.id"
            :href="`/projekte/${project.id}/zahlungen/${invoice.id}`"
            :title="invoice.number"
            :state="invoice.settled ? 'done' : 'open'"
            :note="invoice.issuedOn ? `${invoice.status} · ${shortDate(invoice.issuedOn)}` : invoice.status"
            :aside="formatEuros(invoice.amount)"
            :aside-open="!invoice.settled"
          />
        </div>
      </section>

      <!-- P-03 (#222): the questionnaire the inquiry screen has always linked
           to and nobody could fill in. Absent until the studio shares one. -->
      <section v-if="project.questionnaires.length" class="pt-12">
        <div :class="BAND">
          <PortalLabel class="mb-6">{{ $t('portal.questionnairesTitle') }}</PortalLabel>
          <PortalRow
            v-for="sheet in project.questionnaires"
            :key="sheet.id"
            :href="`/projekte/${project.id}/fragebogen/${sheet.id}`"
            :title="sheet.name"
            :state="sheet.openCount ? 'open' : 'done'"
            :note="sheet.openCount
              ? $t('portal.questionsOpen', { open: sheet.openCount, total: sheet.count })
              : $t('portal.questionsDone')"
          />
        </div>
      </section>

      <!-- The same details the inquiry let them edit, read-only now: from
           `gebucht` the date and the place are agreed. In their
           place stands the way a change gets discussed. -->
      <section class="pt-12">
        <div :class="BAND">
          <PortalLabel class="mb-4">{{ $t('portal.notedTitle') }}</PortalLabel>
          <dl class="m-0 grid grid-cols-[148px_1fr] gap-x-6 gap-y-3 max-[700px]:grid-cols-1 max-[700px]:gap-y-0">
            <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.appointment') }}</dt>
            <dd class="m-0 text-[14.5px] tabular-nums">
              {{ project.details.date ? longDate(project.details.date) : $t('portal.dateOpen') }}
              <template v-if="project.details.callTime"> · {{ project.details.callTime }}</template>
            </dd>
            <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.place') }}</dt>
            <dd class="m-0 text-[14.5px]">{{ project.details.venue ?? '—' }}</dd>
            <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.reachUs') }}</dt>
            <dd class="m-0 text-[14.5px]">
              {{ [project.details.email, project.details.phone].filter(Boolean).join(' · ') }}
              <Link class="ml-2 text-[13px] text-muted-foreground hover:text-foreground" href="/einstellungen">
                {{ $t('common.edit').toLowerCase() }}
              </Link>
            </dd>
          </dl>

          <p v-if="studio.messagingEnabled" class="mt-6 flex flex-wrap items-center gap-2 text-[13px] text-muted-foreground">
            <svg class="size-3.5 flex-none fill-none stroke-current stroke-[1.5]" viewBox="0 0 24 24"><path d="M7 11V8a5 5 0 0110 0v3M5 11h14v9H5z" /></svg>
            <a class="text-foreground" :href="messageHref">{{ $t('portal.requestChange') }}</a>
          </p>
        </div>
      </section>

      <PortalSuggestion v-if="offers.suggestion" :suggestion="offers.suggestion" class="mt-[var(--band)]" />

      <PortalOffers :shelf="offers.shelf" class="mt-[var(--band)]" />

      <section v-if="reachable(studio)" class="pt-12">
        <div :class="BAND">
          <PortalLabel class="mb-4">{{ $t('portal.contactTitle') }}</PortalLabel>
          <PortalPerson :studio="studio" :message-href="messageHref" />
        </div>
      </section>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
