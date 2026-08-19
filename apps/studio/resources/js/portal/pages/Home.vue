<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalPerson from '@/portal/components/PortalPerson.vue'
import { formatEuros } from '@/admin/lib/money'

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

</script>

<template>
  <PortalHeader :contact="contact" />

  <main class="mx-auto max-w-[1060px] px-8 pt-11 pb-16">
    <h1>{{ contact.name }}</h1>
    <p v-if="welcomeText" class="mt-5 max-w-[56ch] text-[16px] leading-[1.6] font-normal text-muted-foreground">{{ welcomeText }}</p>

    <div class="mt-11 grid items-start gap-11 min-[1080px]:grid-cols-[minmax(0,1fr)_320px]">
      <div>
        <!-- Equal-ranked, by year. No booking type is privileged. -->
        <section v-if="bookings.length">
          <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.bookingsTitle') }}</p>

          <div v-if="showFilters" class="mb-5 flex flex-wrap gap-1.5">
            <button class="rounded-full border border-border px-[13px] py-[7px] text-[13px] text-muted-foreground hover:border-line-hover hover:text-foreground" :class="filter === 'all' && 'border-foreground bg-foreground font-semibold text-background'" type="button" @click="filter = 'all'">
              {{ $t('portal.filterAll') }}<span class="ml-[5px] tabular-nums opacity-55">{{ bookings.length }}</span>
            </button>
            <button
              v-for="phase in (['open', 'upcoming', 'delivered'] as Phase[])"
              v-show="phaseCounts[phase]"
              :key="phase"
              class="rounded-full border border-border px-[13px] py-[7px] text-[13px] text-muted-foreground hover:border-line-hover hover:text-foreground"
              :class="filter === phase && 'border-foreground bg-foreground font-semibold text-background'"
              type="button"
              @click="filter = phase"
            >
              {{ $t(`portal.phase.${phase}`) }}<span class="ml-[5px] tabular-nums opacity-55">{{ phaseCounts[phase] }}</span>
            </button>
          </div>

          <template v-for="[year, rows] in years" :key="year">
            <div class="mt-8 mb-3.5 flex items-baseline gap-3.5 text-[13px] leading-none font-semibold tabular-nums">
              <span>{{ year === 'offen' ? $t('portal.dateOpen') : year }}</span>
              <button
                v-if="rows.length > ROLLUP_FROM"
                class="cursor-pointer border-0 bg-transparent p-0 text-[12.5px] leading-none font-normal text-muted-foreground hover:text-foreground"
                type="button"
                @click="opened[year] = !opened[year]"
              >
                {{ opened[year] ? $t('portal.foldYear') : $t('portal.unfoldYear') }}
              </button>
              <span class="ml-auto text-[12px] leading-none font-normal text-faint">{{ $t('portal.bookingCount', rows.length) }}</span>
            </div>

            <div v-if="!folded(year, rows)" class="border-t border-border">
              <Link v-for="booking in rows" :key="booking.id" class="flex items-center gap-5 border-b border-border py-5 text-foreground no-underline hover:bg-muted" :href="`/projekte/${booking.id}`">
                <span class="min-w-0 flex-1">
                  <span class="mb-[3px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ booking.type }}</span>
                  <h3 class="m-0 text-[15px] leading-[1.3] font-semibold">{{ booking.name }}</h3>
                  <div class="mt-0.5 text-[12.5px] leading-[1.4] font-normal tabular-nums text-muted-foreground">
                    {{ [booking.date ? shortDate(booking.date) : $t('portal.dateOpen'), booking.venue].filter(Boolean).join(' · ') }}
                  </div>
                </span>
                <span class="text-right text-[12.5px] leading-[1.4] font-normal whitespace-nowrap text-muted-foreground">
                  {{ $t(`portal.phase.${booking.phase}`) }}
                  <template v-if="booking.openAmount">
                    · {{ $t('portal.paymentsOpen', { amount: formatEuros(booking.openAmount) }) }}
                  </template>
                </span>
              </Link>
            </div>
          </template>
        </section>

        <p v-else class="text-[11.5px] leading-[1.45] text-faint">{{ $t('portal.nothingShared') }}</p>

        <section v-if="galleries.length" class="mt-11">
          <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.galleriesTitle') }}</p>
          <div class="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-5">
            <a v-for="gallery in galleries" :key="gallery.id" class="text-foreground no-underline" :href="`/galerien/${gallery.id}`">
              <span class="mb-3.5 block aspect-4/3 overflow-hidden rounded-md bg-muted">
                <img
                  v-if="gallery.cover"
                  :src="`${gallery.cover}?w=800&fmt=jpeg`"
                  alt=""
                  loading="lazy"
                  class="block size-full object-cover"
                />
              </span>
              <b class="block text-[14px] leading-[1.3] font-semibold">{{ gallery.title }}</b>
              <span class="mt-1.5 flex flex-col gap-[3px] text-[12px] leading-[1.4] font-normal tabular-nums text-muted-foreground">
                {{ $t('portal.imageCount', gallery.count) }}
                <template v-if="gallery.expiresAt">
                  · {{ $t('gallery.onlineUntil', { date: shortDate(gallery.expiresAt) }) }}
                </template>
              </span>
            </a>
          </div>
        </section>
      </div>

      <div class="order-first flex flex-col min-[1080px]:order-none">
        <!-- Absent when nothing is open. An empty box would say the studio
             has nothing for them, which is a different statement. -->
        <section v-if="pending.length">
          <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.pendingTitle') }}</p>
          <div class="border-t border-border">
            <a v-for="(row, index) in pending" :key="index" class="flex items-center gap-5 border-b border-border py-5 text-foreground no-underline hover:bg-muted" :href="row.href">
              <span style="flex: 1">
                <h3 class="m-0 flex-1 text-[14px] leading-[1.3] font-semibold">{{ row.kind === 'message' ? row.subject : $t(`portal.pending.${row.kind}`) }}</h3>
                <div v-if="row.dueOn" class="mt-0.5 text-[12.5px] leading-[1.4] font-normal text-muted-foreground">
                  {{ $t(row.kind === 'quote' ? 'portal.validUntil' : 'portal.dueOn', { date: shortDate(row.dueOn) }) }}
                </div>
                <div v-else-if="row.kind === 'message'" class="mt-0.5 text-[12.5px] leading-[1.4] font-normal text-muted-foreground">{{ $t('portal.pending.message') }}</div>
                <div v-if="row.booking" class="mt-0.5 text-[12.5px] leading-[1.4] font-normal text-muted-foreground">{{ row.booking }}</div>
              </span>
              <span
                v-if="row.amount"
                class="text-[14px] leading-none font-semibold tabular-nums whitespace-nowrap"
                :class="row.overdue && 'text-destructive'"
              >
                {{ formatEuros(row.amount) }}
              </span>
            </a>
          </div>
        </section>

        <section class="mt-11 first:mt-0">
          <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.contactTitle') }}</p>
          <PortalPerson :name="studio.name">
            <Link v-if="studio.messagingEnabled" href="/nachrichten">{{ $t('portal.messagesLinkTitle') }}</Link>
          </PortalPerson>
        </section>
      </div>
    </div>
  </main>

  <PortalFooter />
</template>
