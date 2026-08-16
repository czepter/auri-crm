<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import { Button } from '@/ui/button'
import { initials } from '@/portal/lib/initials'
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

  <header v-if="heroImage" class="hero">
    <img :src="`${heroImage}?w=2400&fmt=jpeg`" alt="" />
    <div class="herotext">
      <div class="kick">{{ project.type }}</div>
      <h1>{{ project.name }}</h1>
      <p v-if="project.date || project.venue" class="meta">
        {{ [project.date ? longDate(project.date) : null, project.venue].filter(Boolean).join(' · ') }}
      </p>
    </div>
  </header>

  <main class="pwrap wide">
    <template v-if="!heroImage">
      <div class="kick">{{ project.type }}</div>
      <h1>{{ project.name }}</h1>
    </template>

    <p v-if="statement" class="statement" :style="heroImage ? '' : 'margin-top: var(--s5)'">
      {{ statement === 'countdown' ? $t('portal.daysUntil', { days: daysLeft }) : $t(`portal.${statement}`) }}
    </p>

    <!-- The emotional core, and the one thing the old screen had no way in
         to at all. -->
    <section v-if="project.gallery" class="psec" style="margin-top: var(--s6)">
      <div class="strip">
        <a v-for="src in project.gallery.strip" :key="src" :href="`/galerien/${project.gallery.id}`">
          <img :src="`${src}?w=800&fmt=jpeg`" alt="" loading="lazy" />
        </a>
      </div>

      <div class="stripfoot">
        <div>
          <b>{{ project.gallery.title }}</b>
          <div class="hint">
            {{ $t('portal.imageCount', project.gallery.count) }}
            <template v-if="project.gallery.expiresAt">
              · {{ $t('gallery.onlineUntil', { date: shortDate(project.gallery.expiresAt) }) }}
            </template>
          </div>
        </div>
        <span class="stripactions">
          <Button as="a" :href="`/galerien/${project.gallery.id}`">
            {{ $t('portal.viewAllImages', { count: project.gallery.count }) }}
          </Button>
          <!-- P-06 (#225): the couple's own side of the gallery — the link
               they hand out, its gates, and their lists. -->
          <Button as="a" variant="ghost" :href="`/galerien/${project.gallery.id}/eure-auswahl`">
            {{ $t('gallery.listsTitle') }}
          </Button>
        </span>
      </div>
    </section>

    <section v-if="project.shoots.length" class="psec">
      <p class="lab">{{ $t('portal.shootsTitle') }}</p>
      <div class="rail">
        <div v-for="shoot in project.shoots" :key="shoot.id" class="shoot">
          <div class="when">
            <b>{{ shoot.date ? shortDate(shoot.date) : $t('portal.dateOpen') }}</b>
            <template v-if="shoot.date">
              {{ [weekday(shoot.date), shoot.callTime].filter(Boolean).join(' · ') }}
            </template>
          </div>
          <div>
            <div class="what">{{ shoot.title }}</div>
            <div v-if="shoot.venue" class="where">{{ shoot.venue }}</div>
          </div>
          <div class="state">{{ shoot.status }}</div>
        </div>
      </div>
    </section>

    <!-- State in the row, not behind a click. Three grey text links before. -->
    <section v-if="project.documents.length" class="psec">
      <p class="lab">{{ $t('portal.documentsTitle') }}</p>
      <div class="pend">
        <a v-for="document in project.documents" :key="document.kind" :href="document.href">
          <span style="flex: 1">
            <h3>{{ $t(`portal.document.${document.kind}`) }}</h3>
            <div class="sub">
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
          <span v-if="document.amount" class="amount">{{ formatEuros(document.amount) }}</span>
        </a>
      </div>
    </section>

    <!-- The same details the inquiry let them edit, read-only now: from
         `gebucht` the date and the place are contract content. In their
         place stands the way a change gets discussed. -->
    <section class="psec">
      <p class="lab">{{ $t('portal.notedTitle') }}</p>
      <dl class="facts">
        <dt>{{ $t('portal.appointment') }}</dt>
        <dd class="num">
          {{ project.details.date ? longDate(project.details.date) : $t('portal.dateOpen') }}
          <template v-if="project.details.callTime"> · {{ project.details.callTime }}</template>
        </dd>
        <dt>{{ $t('portal.place') }}</dt>
        <dd>{{ project.details.venue ?? '—' }}</dd>
        <dt>{{ $t('portal.reachUs') }}</dt>
        <dd>
          {{ [project.details.email, project.details.phone].filter(Boolean).join(' · ') }}
          <Link href="/einstellungen">{{ $t('common.edit').toLowerCase() }}</Link>
        </dd>
      </dl>

      <p v-if="studio.messagingEnabled" class="lockednote">
        <svg viewBox="0 0 24 24"><path d="M7 11V8a5 5 0 0110 0v3M5 11h14v9H5z" /></svg>
        <a :href="`/nachrichten?betrifft=${project.id}`">{{ $t('portal.requestChange') }}</a>
      </p>
    </section>

    <section class="psec">
      <p class="lab">{{ $t('portal.contactTitle') }}</p>
      <div class="person">
        <span class="av">{{ initials(studio.name) }}</span>
        <span>
          <b>{{ studio.name }}</b>
          <a v-if="studio.messagingEnabled" class="role" :href="`/nachrichten?betrifft=${project.id}`">
            {{ $t('portal.messagesLinkTitle') }}
          </a>
        </span>
      </div>
    </section>
  </main>

  <PortalFooter />
</template>
