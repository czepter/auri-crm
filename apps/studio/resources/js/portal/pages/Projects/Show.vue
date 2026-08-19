<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import PortalPerson from '@/portal/components/PortalPerson.vue'
import { Button } from '@/ui/button'
import { formatEuros } from '@/admin/lib/money'

type Shoot = {
  id: string
  title: string
  date: string | null
  callTime: string | null
  venue: string | null
  status: string
}

type Document = {
  kind: 'payments' | 'contract' | 'quote' | 'messages'
  href: string
  dueOn: string | null
  signedOn?: string | null
  acceptedOn?: string | null
  amount: number | null
  settled: boolean
}

const props = defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  studio: { name: string | null; messagingEnabled: boolean }
  project: {
    id: string
    name: string
    type: string
    date: string | null
    venue: string | null
    shoots: Shoot[]
    gallery: { id: string; title: string | null; count: number; expiresAt: string | null; strip: string[] } | null
    documents: Document[]
    questionnaires: { id: string; name: string; count: number; openCount: number }[]
    details: { date: string | null; callTime: string | null; venue: string | null; email: string | null; phone: string | null }
  }
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
</script>

<template>
  <PortalHeader :contact="contact" :show-switcher="showSwitcher" />

  <header v-if="heroImage" class="relative after:absolute after:inset-0 after:bg-[linear-gradient(0deg,rgba(20,16,14,0.72)_0%,rgba(20,16,14,0.15)_55%,rgba(20,16,14,0)_100%)] after:content-['']">
    <img class="block h-[min(62vh,560px)] w-full object-cover" :src="`${heroImage}?w=2400&fmt=jpeg`" alt="" />
    <div class="absolute inset-x-0 bottom-11 z-2 mx-auto max-w-[1060px] px-8 text-white">
      <div class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint text-white/72">{{ project.type }}</div>
      <h1 class="mt-1.5 text-[clamp(30px,5vw,46px)] text-white">{{ project.name }}</h1>
      <p v-if="project.date || project.venue" class="mt-3.5 text-[14.5px] leading-[1.5] font-normal tabular-nums text-white/85">
        {{ [project.date ? longDate(project.date) : null, project.venue].filter(Boolean).join(' · ') }}
      </p>
    </div>
  </header>

  <PortalWrap wide>
    <template v-if="!heroImage">
      <div class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ project.type }}</div>
      <h1>{{ project.name }}</h1>
    </template>

    <p v-if="statement" class="m-0 text-[22px] leading-[1.45] font-normal tracking-[-0.02em]" :class="!heroImage && 'mt-8'">
      {{ statement === 'countdown' ? $t('portal.daysUntil', { days: daysLeft }) : $t(`portal.${statement}`) }}
    </p>

    <!-- The emotional core, and the one thing the old screen had no way in
         to at all. -->
    <section v-if="project.gallery" class="mt-11 [&:not(:first-of-type)]:mt-11">
      <div class="grid grid-cols-4 gap-3.5 max-[560px]:grid-cols-2">
        <a
          v-for="src in project.gallery.strip"
          :key="src"
          class="block aspect-square overflow-hidden rounded-md bg-muted"
          :href="`/galerien/${project.gallery.id}`"
        >
          <img class="block size-full object-cover" :src="`${src}?w=800&fmt=jpeg`" alt="" loading="lazy" />
        </a>
      </div>

      <div class="mt-5 flex flex-wrap items-end justify-between gap-5">
        <div>
          <b>{{ project.gallery.title }}</b>
          <div class="text-[11.5px] leading-[1.45] text-faint">
            {{ $t('portal.imageCount', project.gallery.count) }}
            <template v-if="project.gallery.expiresAt">
              · {{ $t('gallery.onlineUntil', { date: shortDate(project.gallery.expiresAt) }) }}
            </template>
          </div>
        </div>
        <!-- One gallery for both audiences: the couple's own half — their
             link, its gates, their lists — sits under the pictures. -->
        <Button as="a" :href="`/galerien/${project.gallery.id}`">
          {{ $t('portal.viewAllImages', { count: project.gallery.count }) }}
        </Button>
      </div>
    </section>

    <section v-if="project.shoots.length" class="[&:not(:first-of-type)]:mt-11">
      <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.shootsTitle') }}</p>
      <div class="flex flex-col">
        <div v-for="shoot in project.shoots" :key="shoot.id" class="flex items-center gap-5 border-b border-border py-5 max-[560px]:flex-wrap">
          <div class="w-[150px] flex-none text-[12.5px] leading-[1.5] font-normal tabular-nums text-muted-foreground max-[560px]:w-full">
            <!-- normal-nums: die font-Kurzschrift der abgeloesten Regel setzt
                 font-variant-numeric zurueck, das tabular-nums des Elternteils
                 gilt hier also nicht. -->
            <b class="block text-[14px] leading-[1.3] font-semibold normal-nums text-foreground">{{ shoot.date ? shortDate(shoot.date) : $t('portal.dateOpen') }}</b>
            <template v-if="shoot.date">
              {{ [weekday(shoot.date), shoot.callTime].filter(Boolean).join(' · ') }}
            </template>
          </div>
          <div>
            <div class="text-[14px] leading-[1.3] font-semibold">{{ shoot.title }}</div>
            <div v-if="shoot.venue" class="mt-0.5 text-[12.5px] leading-[1.4] font-normal text-muted-foreground">{{ shoot.venue }}</div>
          </div>
          <div class="ml-auto text-[12.5px] leading-[1.4] font-normal whitespace-nowrap text-muted-foreground">{{ shoot.status }}</div>
        </div>
      </div>
    </section>

    <!-- State in the row, not behind a click. Three grey text links before. -->
    <section v-if="project.documents.length" class="[&:not(:first-of-type)]:mt-11">
      <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.documentsTitle') }}</p>
      <div class="border-t border-border">
        <a v-for="document in project.documents" :key="document.kind" class="flex items-center gap-5 border-b border-border py-5 text-foreground no-underline hover:bg-muted" :href="document.href">
          <span style="flex: 1">
            <h3 class="m-0 flex-1 text-[14px] leading-[1.3] font-semibold">{{ $t(`portal.document.${document.kind}`) }}</h3>
            <div class="mt-0.5 text-[12.5px] leading-[1.4] font-normal text-muted-foreground">
              <template v-if="document.kind === 'payments'">
                {{ document.settled ? $t('portal.allSettled') : $t('portal.dueOn', { date: shortDate(document.dueOn!) }) }}
              </template>
              <template v-else-if="document.kind === 'contract'">
                {{ document.signedOn ? $t('portal.signedOn', { date: shortDate(document.signedOn) }) : $t('portal.notSignedYet') }}
              </template>
              <template v-else-if="document.kind === 'quote'">
                {{ document.acceptedOn ? $t('portal.quoteAccepted', { date: shortDate(document.acceptedOn) }) : $t('portal.quoteOpen') }}
              </template>
              <template v-else>{{ $t('portal.messagesAbout') }} {{ project.name }}</template>
            </div>
          </span>
          <span v-if="document.amount" class="text-[14px] leading-none font-semibold tabular-nums whitespace-nowrap">{{ formatEuros(document.amount) }}</span>
        </a>
      </div>
    </section>


    <!-- P-03 (#222): the questionnaire the inquiry screen has always linked
         to and nobody could fill in. Absent until the studio shares one. -->
    <section v-if="project.questionnaires.length" class="[&:not(:first-of-type)]:mt-11">
      <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.questionnairesTitle') }}</p>
      <div class="border-t border-border">
        <a
          v-for="sheet in project.questionnaires"
          :key="sheet.id"
          class="flex items-center gap-5 border-b border-border py-5 text-foreground no-underline hover:bg-muted"
          :href="`/projekte/${project.id}/fragebogen/${sheet.id}`"
        >
          <span style="flex: 1">
            <h3 class="m-0 flex-1 text-[14px] leading-[1.3] font-semibold">{{ sheet.name }}</h3>
            <div class="mt-0.5 text-[12.5px] leading-[1.4] font-normal text-muted-foreground">
              {{ sheet.openCount
                ? $t('portal.questionsOpen', { open: sheet.openCount, total: sheet.count })
                : $t('portal.questionsDone') }}
            </div>
          </span>
        </a>
      </div>
    </section>

    <!-- The same details the inquiry let them edit, read-only now: from
         `gebucht` the date and the place are contract content. In their
         place stands the way a change gets discussed. -->
    <section class="[&:not(:first-of-type)]:mt-11">
      <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.notedTitle') }}</p>
      <dl class="m-0 grid grid-cols-[150px_minmax(0,1fr)] gap-x-5 gap-y-3.5 text-[14px] leading-[1.55] font-normal max-[560px]:grid-cols-[minmax(0,1fr)] max-[560px]:gap-x-5 max-[560px]:gap-y-0.5">
        <dt class="text-muted-foreground">{{ $t('portal.appointment') }}</dt>
        <dd class="m-0 tabular-nums max-[560px]:mb-3.5">
          {{ project.details.date ? longDate(project.details.date) : $t('portal.dateOpen') }}
          <template v-if="project.details.callTime"> · {{ project.details.callTime }}</template>
        </dd>
        <dt class="text-muted-foreground">{{ $t('portal.place') }}</dt>
        <dd class="m-0 max-[560px]:mb-3.5">{{ project.details.venue ?? '—' }}</dd>
        <dt class="text-muted-foreground">{{ $t('portal.reachUs') }}</dt>
        <dd class="m-0 max-[560px]:mb-3.5">
          {{ [project.details.email, project.details.phone].filter(Boolean).join(' · ') }}
          <Link href="/einstellungen">{{ $t('common.edit').toLowerCase() }}</Link>
        </dd>
      </dl>

      <p v-if="studio.messagingEnabled" class="mt-8 flex items-center gap-2 text-[13px] leading-[1.5] font-normal text-muted-foreground">
        <svg class="size-3.5 flex-none fill-none stroke-current stroke-[1.6]" viewBox="0 0 24 24"><path d="M7 11V8a5 5 0 0110 0v3M5 11h14v9H5z" /></svg>
        <a :href="`/nachrichten?betrifft=${project.id}`">{{ $t('portal.requestChange') }}</a>
      </p>
    </section>

    <section class="[&:not(:first-of-type)]:mt-11">
      <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.contactTitle') }}</p>
      <PortalPerson :name="studio.name">
        <a v-if="studio.messagingEnabled" :href="`/nachrichten?betrifft=${project.id}`">
          {{ $t('portal.messagesLinkTitle') }}
        </a>
      </PortalPerson>
    </section>
  </PortalWrap>

  <PortalFooter />
</template>
