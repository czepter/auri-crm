<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import { formatEuros } from '@/admin/lib/money'
import { initials } from '@/portal/lib/initials'

type Phase = 'open' | 'upcoming' | 'delivered'

type Pending = {
  kind: 'installment' | 'quote' | 'message'
  amount: number | null
  dueOn: string | null
  overdue: boolean
  booking: string | null
  subject?: string
  href: string
}

type Booking = {
  id: string
  name: string
  type: string
  date: string | null
  year: number | null
  venue: string | null
  phase: Phase
  openAmount: number | null
}

const props = defineProps<{
  contact: { name: string }
  // T-08: the portal home's own welcome text (studio_settings.portal_welcome_text) —
  // null until a studio sets one, shown nowhere else.
  welcomeText: string | null
  pending: Pending[]
  bookings: Booking[]
  phaseCounts: Partial<Record<Phase, number>>
  galleries: { id: string; title: string | null; count: number; expiresAt: string | null; cover: string | null }[]
  studio: { name: string | null; messagingEnabled: boolean }
}>()

// P-02: the filter only earns its place once there is a stock to filter. At
// three bookings it would be decoration.
const FILTER_FROM = 8
const filter = ref<Phase | 'all'>('all')
const showFilters = computed(() => props.bookings.length >= FILTER_FROM)

const visible = computed(() =>
  filter.value === 'all' ? props.bookings : props.bookings.filter((b) => b.phase === filter.value),
)

// Years are a finding aid, never a ranking — a corporate date and a wedding
// sit side by side inside one. A year is folded only because forty rows do
// not fit, so it always opens again.
const ROLLUP_FROM = 6
const opened = ref<Record<string, boolean>>({})

const years = computed(() => {
  const groups = new Map<string, Booking[]>()

  for (const booking of visible.value) {
    const key = booking.year === null ? 'offen' : String(booking.year)
    groups.set(key, [...(groups.get(key) ?? []), booking])
  }

  return [...groups.entries()]
})

function folded(key: string, rows: Booking[]) {
  return rows.length > ROLLUP_FROM && !opened.value[key]
}

function shortDate(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const studioInitials = computed(() => initials(props.studio.name))
</script>

<template>
  <PortalHeader :contact="contact" />

  <main class="pwrap wide">
    <h1>{{ contact.name }}</h1>
    <p v-if="welcomeText" class="plead">{{ welcomeText }}</p>

    <div class="phome" style="margin-top: var(--s6)">
      <div>
        <!-- Equal-ranked, by year. No booking type is privileged. -->
        <section v-if="bookings.length" class="psec">
          <p class="lab">{{ $t('portal.bookingsTitle') }}</p>

          <div v-if="showFilters" class="filters">
            <button class="fpill" :class="{ on: filter === 'all' }" type="button" @click="filter = 'all'">
              {{ $t('portal.filterAll') }}<span class="n">{{ bookings.length }}</span>
            </button>
            <button
              v-for="phase in (['open', 'upcoming', 'delivered'] as Phase[])"
              v-show="phaseCounts[phase]"
              :key="phase"
              class="fpill"
              :class="{ on: filter === phase }"
              type="button"
              @click="filter = phase"
            >
              {{ $t(`portal.phase.${phase}`) }}<span class="n">{{ phaseCounts[phase] }}</span>
            </button>
          </div>

          <template v-for="[year, rows] in years" :key="year">
            <div class="yearhead">
              <span>{{ year === 'offen' ? $t('portal.dateOpen') : year }}</span>
              <button v-if="rows.length > ROLLUP_FROM" type="button" @click="opened[year] = !opened[year]">
                {{ opened[year] ? $t('portal.foldYear') : $t('portal.unfoldYear') }}
              </button>
              <span class="n">{{ $t('portal.bookingCount', rows.length) }}</span>
            </div>

            <div v-if="!folded(year, rows)" class="entries">
              <Link v-for="booking in rows" :key="booking.id" :href="`/projekte/${booking.id}`">
                <span class="body">
                  <span class="kick">{{ booking.type }}</span>
                  <h3>{{ booking.name }}</h3>
                  <div class="sub">
                    {{ [booking.date ? shortDate(booking.date) : $t('portal.dateOpen'), booking.venue].filter(Boolean).join(' · ') }}
                  </div>
                </span>
                <span class="state">
                  {{ $t(`portal.phase.${booking.phase}`) }}
                  <template v-if="booking.openAmount">
                    · {{ $t('portal.paymentsOpen', { amount: formatEuros(booking.openAmount) }) }}
                  </template>
                </span>
              </Link>
            </div>
          </template>
        </section>

        <p v-else class="hint">{{ $t('portal.nothingShared') }}</p>

        <section v-if="galleries.length" class="psec">
          <p class="lab">{{ $t('portal.galleriesTitle') }}</p>
          <div class="gcards">
            <a v-for="gallery in galleries" :key="gallery.id" :href="`/galerien/${gallery.id}`">
              <span class="shot">
                <img v-if="gallery.cover" :src="`${gallery.cover}?w=800&fmt=jpeg`" alt="" loading="lazy" />
              </span>
              <b>{{ gallery.title }}</b>
              <span class="meta">
                {{ $t('portal.imageCount', gallery.count) }}
                <template v-if="gallery.expiresAt">
                  · {{ $t('gallery.onlineUntil', { date: shortDate(gallery.expiresAt) }) }}
                </template>
              </span>
            </a>
          </div>
        </section>
      </div>

      <div class="rail">
        <!-- Absent when nothing is open. An empty box would say the studio
             has nothing for them, which is a different statement. -->
        <section v-if="pending.length" class="psec">
          <p class="lab">{{ $t('portal.pendingTitle') }}</p>
          <div class="pend">
            <a v-for="(row, index) in pending" :key="index" :href="row.href">
              <span style="flex: 1">
                <h3>{{ row.kind === 'message' ? row.subject : $t(`portal.pending.${row.kind}`) }}</h3>
                <div v-if="row.dueOn" class="sub">
                  {{ $t(row.kind === 'quote' ? 'portal.validUntil' : 'portal.dueOn', { date: shortDate(row.dueOn) }) }}
                </div>
                <div v-else-if="row.kind === 'message'" class="sub">{{ $t('portal.pending.message') }}</div>
                <div v-if="row.booking" class="where">{{ row.booking }}</div>
              </span>
              <span v-if="row.amount" class="amount" :class="{ late: row.overdue }">
                {{ formatEuros(row.amount) }}
              </span>
            </a>
          </div>
        </section>

        <section class="psec">
          <p class="lab">{{ $t('portal.contactTitle') }}</p>
          <div class="person">
            <span class="av">{{ studioInitials }}</span>
            <span>
              <b>{{ studio.name }}</b>
              <span v-if="studio.messagingEnabled" class="role">
                <Link href="/nachrichten">{{ $t('portal.messagesLinkTitle') }}</Link>
              </span>
            </span>
          </div>
        </section>
      </div>
    </div>
  </main>

  <PortalFooter />
</template>
