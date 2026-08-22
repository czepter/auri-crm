<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import PortalDot from '@/portal/components/PortalDot.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalPerson from '@/portal/components/PortalPerson.vue'
import PortalRow from '@/portal/components/PortalRow.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { reachable, type Studio } from '@/portal/lib/studio'
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
  cover: string | null
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
  studio: Studio
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

function phaseState(phase: Phase) {
  return phase === 'delivered' ? 'done' : phase === 'open' ? 'open' : 'neutral'
}

const FILTER_PILL
  = 'cursor-pointer rounded-[var(--r-pill)] border border-line-hover px-[15px] py-2.5 text-[13px] leading-none font-medium text-muted-foreground transition-colors hover:border-foreground hover:text-foreground'
</script>

<template>
  <PortalShell>
    <PortalHeader />

    <PortalWrap class="pb-[var(--band)]">
      <section class="pt-[var(--band)]">
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(38px,14.3px+6.08vw,86px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ contact.name }}
        </h1>
        <p v-if="welcomeText" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-6 max-w-[52ch] text-[17px] leading-[1.55] text-muted-foreground">
          {{ welcomeText }}
        </p>
      </section>

      <!-- Two columns from 1080px: what the client reads through on the left,
           what concerns them alongside. Below that one column with the open
           items first — on a phone "is anything waiting on us?" is answered
           before the archive. -->
      <div class="grid items-start gap-[var(--band)] pt-[var(--band)] min-[1080px]:grid-cols-[minmax(0,1fr)_348px] min-[1080px]:gap-x-12">
        <div class="order-first grid gap-12 min-[1080px]:sticky min-[1080px]:top-[88px] min-[1080px]:order-none min-[1080px]:col-start-2">
          <!-- Absent when nothing is open. An empty box would say the studio
               has nothing for them, which is a different statement. -->
          <section v-if="pending.length" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none">
            <PortalLabel class="mb-6">{{ $t('portal.pendingTitle') }}</PortalLabel>
            <!-- An instalment is money already owed; a quote's total is a
                 figure, not a debt. Only the first wears the action colour. -->
            <div class="border-t border-border">
              <PortalRow
                v-for="(row, index) in pending"
                :key="index"
                :href="row.href"
                :title="row.kind === 'message' ? (row.subject ?? $t('portal.pending.message')) : $t(`portal.pending.${row.kind}`)"
                state="open"
                :note="row.dueOn
                  ? $t(row.kind === 'quote' ? 'portal.validUntil' : 'portal.dueOn', { date: shortDate(row.dueOn) })
                  : row.kind === 'message' ? $t('portal.pending.message') : null"
                :where="row.booking"
                :aside="row.amount ? formatEuros(row.amount) : null"
                :aside-open="row.kind === 'installment'"
              />
            </div>
          </section>

          <!-- A person, not a contact form. Absent when there is nobody to
               name: a label over an empty card is not an empty state. -->
          <section v-if="reachable(studio)" class="animate-rise [animation-delay:320ms] motion-reduce:animate-none">
            <PortalLabel class="mb-6">{{ $t('portal.contactTitle') }}</PortalLabel>
            <PortalPerson :studio="studio" message-href="/nachrichten" />
          </section>
        </div>

        <div class="grid gap-[var(--band)] min-[1080px]:col-start-1 min-[1080px]:row-start-1">
          <!-- Equal-ranked, by year. No booking type is privileged. -->
          <section v-if="bookings.length" class="animate-rise [animation-delay:320ms] motion-reduce:animate-none">
            <PortalLabel>{{ $t('portal.bookingsTitle') }}</PortalLabel>

            <div v-if="showFilters" class="mt-6 flex flex-wrap gap-2">
              <button
                :class="[FILTER_PILL, filter === 'all' && 'border-foreground! bg-foreground font-semibold text-background!']"
                type="button"
                @click="filter = 'all'"
              >
                {{ $t('portal.filterAll') }}<span class="ml-[5px] tabular-nums opacity-55">{{ bookings.length }}</span>
              </button>
              <button
                v-for="phase in (['open', 'upcoming', 'delivered'] as Phase[])"
                v-show="phaseCounts[phase]"
                :key="phase"
                :class="[FILTER_PILL, filter === phase && 'border-foreground! bg-foreground font-semibold text-background!']"
                type="button"
                @click="filter = phase"
              >
                {{ $t(`portal.phase.${phase}`) }}<span class="ml-[5px] tabular-nums opacity-55">{{ phaseCounts[phase] }}</span>
              </button>
            </div>

            <template v-for="[year, rows] in years" :key="year">
              <div class="flex items-baseline gap-4 pt-8 pb-3 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">
                <span>{{ year === 'offen' ? $t('portal.dateOpen') : year }}</span>
                <button
                  v-if="rows.length > ROLLUP_FROM"
                  class="cursor-pointer border-0 bg-transparent p-0 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground hover:text-foreground"
                  type="button"
                  @click="opened[year] = !opened[year]"
                >
                  {{ opened[year] ? $t('portal.foldYear') : $t('portal.unfoldYear') }}
                </button>
                <i class="h-px flex-1 bg-border" />
              </div>

              <template v-if="!folded(year, rows)">
                <Link
                  v-for="booking in rows"
                  :key="booking.id"
                  class="group -mx-4 grid grid-cols-[116px_1fr_auto_22px] items-center gap-6 rounded-[var(--r-sm)] border-b border-border p-4 text-inherit no-underline transition-colors duration-200 hover:bg-sunk max-[700px]:grid-cols-[86px_1fr] max-[700px]:gap-4"
                  :href="`/projekte/${booking.id}`"
                >
                  <img
                    v-if="booking.cover"
                    class="h-[78px] w-[116px] rounded-[10px] bg-sunk object-cover max-[700px]:h-[62px] max-[700px]:w-[86px]"
                    :src="`${booking.cover}?w=400&fmt=jpeg`"
                    alt=""
                    loading="lazy"
                  />
                  <span
                    v-else
                    class="grid h-[78px] w-[116px] place-items-center rounded-[10px] border border-border bg-sunk font-serif text-[26px] leading-none tracking-[-0.02em] text-muted-foreground max-[700px]:h-[62px] max-[700px]:w-[86px] max-[700px]:text-[19px]"
                  >{{ booking.year ?? '—' }}</span>

                  <span class="min-w-0">
                    <span class="mb-1.5 block text-[10.5px] leading-none font-semibold tracking-[0.15em] uppercase text-muted-foreground">{{ booking.type }}</span>
                    <span class="block font-serif text-[23px] leading-[1.2] tracking-[-0.012em] max-[700px]:text-[20px]">{{ booking.name }}</span>
                    <span class="mt-[5px] block text-[13.5px] tabular-nums text-muted-foreground">
                      {{ [booking.date ? shortDate(booking.date) : $t('portal.dateOpen'), booking.venue].filter(Boolean).join(' · ') }}
                    </span>
                  </span>

                  <span class="inline-flex items-center gap-2 text-[12.5px] whitespace-nowrap text-muted-foreground max-[700px]:col-start-2 max-[700px]:mt-1.5">
                    <PortalDot :state="phaseState(booking.phase)" />
                    {{ $t(`portal.phase.${booking.phase}`) }}
                    <template v-if="booking.openAmount">
                      · {{ $t('portal.paymentsOpen', { amount: formatEuros(booking.openAmount) }) }}
                    </template>
                  </span>

                  <!-- A pointer affordance. On a phone the whole row is the
                       target, so it goes rather than being placed twice. -->
                  <span class="justify-self-end text-mark transition-[color,transform] duration-300 group-hover:translate-x-[3px] group-hover:text-foreground max-[700px]:hidden">
                    <svg class="size-[18px] fill-none stroke-current stroke-[1.4]" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
                  </span>
                </Link>
              </template>
            </template>
          </section>

          <p v-else class="animate-rise [animation-delay:320ms] motion-reduce:animate-none text-[15px] text-muted-foreground">{{ $t('portal.nothingShared') }}</p>

          <!-- Straight to the pictures, no detour through the booking. The one
               reason a client opens this portal again after three years. -->
          <section v-if="galleries.length" class="animate-rise [animation-delay:320ms] motion-reduce:animate-none">
            <PortalLabel>{{ $t('portal.galleriesTitle') }}</PortalLabel>
            <div class="mt-6 grid grid-cols-[repeat(auto-fill,minmax(196px,1fr))] gap-4">
              <a v-for="gallery in galleries" :key="gallery.id" class="group text-inherit no-underline" :href="`/galerien/${gallery.id}`">
                <span class="block overflow-hidden rounded-md bg-sunk">
                  <img
                    v-if="gallery.cover"
                    :src="`${gallery.cover}?w=800&fmt=jpeg`"
                    alt=""
                    loading="lazy"
                    class="block h-[152px] w-full object-cover transition-transform duration-1000 ease-[var(--ease)] group-hover:scale-[1.04]"
                  />
                  <span v-else class="block h-[152px]" />
                </span>
                <b class="mt-3 block font-serif text-[19px] leading-[1.25] font-normal tracking-[-0.01em]">{{ gallery.title }}</b>
                <span class="block text-[13px] tabular-nums text-muted-foreground">
                  {{ $t('portal.imageCount', gallery.count) }}
                  <template v-if="gallery.expiresAt">
                    · {{ $t('gallery.onlineUntil', { date: shortDate(gallery.expiresAt) }) }}
                  </template>
                </span>
              </a>
            </div>
          </section>
        </div>
      </div>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
