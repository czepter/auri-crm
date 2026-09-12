<script setup lang="ts">
/*
 * Q-13: das Angebot als Regler. Nur für Angebote mit Stundensatz — ohne Satz
 * rendert Quotes/Show.vue weiter seine Tabelle.
 *
 * Das Raster über dem Regler zeigt Menge, nicht Inhalt: alle 24 Kacheln
 * stehen immer im DOM und blenden zurück, statt entfernt zu werden. Das
 * Wachsen ist eine Blende und kein Umbau.
 */
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { formatEuros } from '@/admin/lib/money'
import { splitStages } from '@/portal/lib/paymentStages'
import { Button } from '@/ui/button'

type Item = App.Quotes.Data.QuoteItemData
type Quote = App.Quotes.Data.QuoteData

const props = defineProps<{
  project: { id: string; name: string }
  quote: Quote
  canAccept: boolean
  expired: boolean
  acceptedAt: string | null
  showcaseCount: number
}>()

const { t } = useI18n()

const TILES = 24
const IMAGES_PER_TILE = 20

const hours = ref(props.quote.hours ?? props.quote.minHours ?? 0)
const chosen = ref(new Set(props.quote.items.filter((i) => i.selectable && i.selected).map((i) => i.id)))

const form = useForm({})
const rejection = computed(() => (form.errors as Record<string, string>).quote ?? (form.errors as Record<string, string>).hours)

const accepted = computed(() => props.quote.status === 'accepted')
const images = computed(() => hours.value * (props.quote.imagesPerHour ?? 0))
const litTiles = computed(() => Math.min(TILES, Math.ceil(images.value / IMAGES_PER_TILE)))

const blocks = computed(() => props.quote.items.filter((item) => item.selectable))
const included = computed(() => props.quote.items.filter((item) => item.isIncluded))
const fixed = computed(() => props.quote.items.filter((item) => !item.isIncluded && !item.selectable))

const total = computed(
  () =>
    hours.value * (props.quote.hourlyRate ?? 0) +
    fixed.value.reduce((sum, item) => sum + item.lineTotal, 0) +
    blocks.value.filter((item) => chosen.value.has(item.id)).reduce((sum, item) => sum + item.lineTotal, 0)
)

const stages = computed(() =>
  splitStages(
    total.value,
    props.quote.paymentStages.map((stage) => ({ label: stage.label, percent: stage.percent, minimum: stage.minimum }))
  )
)

const timeDetail = computed(() =>
  [
    props.quote.timeDescription,
    t('quotes.hoursCount', hours.value),
    props.quote.imagesPerHour ? t('quotes.approxImages', { count: images.value }) : null,
  ]
    .filter(Boolean)
    .join(' · ')
)

// Die Aufstellung sagt, was der Betrag deckt, in der Reihenfolge, in der er
// entsteht: die Stunden, dann was ohne Aufpreis folgt, dann jeder gewählte
// Baustein.
const breakdown = computed(() => [
  { label: props.quote.timeTitle ?? t('quotes.hoursCount', hours.value), amount: hours.value * (props.quote.hourlyRate ?? 0), lead: true },
  ...(props.quote.timeTitle ? [{ label: timeDetail.value, amount: null, lead: false }] : []),
  ...fixed.value.map((item) => ({ label: item.title, amount: item.lineTotal, lead: false })),
  ...(props.quote.timeTitle ? [] : [{ label: t('quotes.editedImages', { count: images.value }), amount: null, lead: false }]),
  ...included.value.map((item) => ({ label: item.outcome ?? item.title, amount: null, lead: false })),
  ...blocks.value.filter((item) => chosen.value.has(item.id)).map((item) => ({ label: item.title, amount: item.lineTotal, lead: false })),
])

function toggle(item: Item) {
  if (accepted.value) return

  const next = new Set(chosen.value)
  next.has(item.id) ? next.delete(item.id) : next.add(item.id)
  chosen.value = next
}

function tileImage(index: number) {
  return props.showcaseCount === 0 ? null : `/schaufenster/${index % props.showcaseCount}`
}

function accept() {
  form
    .transform(() => ({ hours: hours.value, items: [...chosen.value] }))
    .post(`/projekte/${props.project.id}/angebot/annehmen`, { preserveScroll: true })
}
</script>

<template>
  <div class="mt-12 flex items-start gap-12 max-lg:flex-col max-lg:gap-0">
    <div class="min-w-0 flex-1 max-lg:w-full" :class="accepted ? 'pointer-events-none opacity-45' : ''">
      <div class="flex items-baseline gap-3 lg:hidden">
        <span class="text-[46px] leading-[0.94] tracking-[-0.035em] tabular-nums">{{ hours }}</span>
        <span class="text-[16px] leading-[1.2] text-muted-foreground">{{ $t('quotes.hours') }}</span>
        <span class="ml-auto text-[16px] leading-[1.2] font-semibold text-[var(--accent)] tabular-nums">
          {{ $t('quotes.imageCount', { count: images }) }}
        </span>
      </div>

      <input
        v-model.number="hours"
        class="h-11 w-full accent-[var(--accent)]"
        type="range"
        :min="quote.minHours ?? 0"
        :max="quote.maxHours ?? 0"
        step="1"
        :disabled="accepted"
        :aria-label="$t('quotes.hours')"
      />

      <div class="flex justify-between text-[12.5px] leading-none text-muted-foreground tabular-nums">
        <span>{{ $t('quotes.hoursShort', { count: quote.minHours ?? 0 }) }}</span>
        <span>{{ $t('quotes.hoursShort', { count: quote.maxHours ?? 0 }) }}</span>
      </div>

      <!-- Alle 24 bleiben stehen und blenden zurück: das Wachsen ist eine
           Blende, kein Umbau. Ohne Schaufensterbilder entfällt das Raster. -->
      <div
        v-if="showcaseCount > 0"
        class="mt-[18px] grid grid-cols-8 content-start gap-1 lg:grid-cols-12"
      >
        <img
          v-for="index in TILES"
          :key="index"
          class="aspect-square w-full rounded-[4px] object-cover transition-[opacity,transform] duration-[280ms] ease-[cubic-bezier(.22,.61,.36,1)]"
          :class="index <= litTiles ? 'opacity-100 scale-100' : 'opacity-10 scale-[.86]'"
          :src="tileImage(index - 1)!"
          alt=""
          loading="lazy"
        />
      </div>

      <div class="mt-2.5 flex items-baseline justify-between gap-4">
        <span class="text-[13.5px] leading-[1.45] text-muted-foreground">
          {{ $t('quotes.perHour', { amount: formatEuros(quote.hourlyRate ?? 0) }) }}
        </span>
        <span class="hidden text-[16px] leading-[1.2] font-semibold text-[var(--accent)] tabular-nums lg:inline">
          {{ $t('quotes.imageCount', { count: images }) }}
        </span>
      </div>

      <!-- Auf dem Desktop trägt das Rail die Aufstellung; die Chips hier
           sagten dann dasselbe ein zweites Mal. -->
      <div v-if="included.length" class="mt-8 flex flex-wrap gap-2 lg:hidden">
        <span
          v-for="item in included"
          :key="item.id"
          class="inline-flex items-center gap-1.5 rounded-[var(--r-pill)] bg-muted px-3 py-[7px] text-[12.5px] leading-[1.3] font-medium"
        >
          <svg class="size-3 text-[var(--accent)]" viewBox="0 0 24 24"><path d="M4 12.5l5 5L20 6.5" /></svg>
          {{ item.outcome ?? item.title }}
        </span>
      </div>

      <div v-if="blocks.length" class="mt-10 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <button
          v-for="item in blocks"
          :key="item.id"
          class="overflow-hidden rounded-[14px] bg-background text-left transition-[filter,box-shadow] duration-[250ms] ease-out"
          :class="
            chosen.has(item.id)
              ? 'shadow-[0_0_0_1.5px_var(--accent)]'
              : 'shadow-[0_0_0_1px_var(--line)] [filter:grayscale(1)_contrast(.92)]'
          "
          type="button"
          :aria-pressed="chosen.has(item.id)"
          :disabled="accepted"
          @click="toggle(item)"
        >
          <span class="relative block">
            <img
              v-if="item.hasImage"
              class="block h-[104px] w-full object-cover"
              :src="`/projekte/${project.id}/angebot/bild/${item.id}`"
              alt=""
              loading="lazy"
            />
            <span v-else class="block h-[104px] w-full bg-muted"></span>

            <!-- Der Zustand steckt im Bild; das Häkchen sagt ihn noch einmal
                 für alle, die Farbe nicht unterscheiden. -->
            <span
              class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full"
              :class="chosen.has(item.id) ? 'bg-[var(--accent)] text-white' : 'bg-white/80 text-transparent'"
            >
              <svg class="size-[13px]" viewBox="0 0 24 24"><path d="M4 12.5l5 5L20 6.5" /></svg>
            </span>
          </span>

          <span class="block px-3.5 pt-3 pb-3.5">
            <span class="block text-[14.5px] leading-[1.25] font-semibold tracking-[-0.015em]">{{ item.title }}</span>
            <span v-if="item.outcome" class="mt-[3px] block text-[12.5px] leading-[1.4] text-muted-foreground">{{ item.outcome }}</span>
            <span
              class="mt-2.5 block text-[13px] leading-none font-semibold tabular-nums"
              :class="chosen.has(item.id) ? 'text-[var(--accent)]' : 'text-muted-foreground'"
            >+ {{ formatEuros(item.lineTotal) }}</span>
          </span>
        </button>
      </div>
    </div>

    <!-- Desktop: klebendes Rail. Handy: klebende Leiste am Fuß. -->
    <aside
      class="w-[340px] flex-none lg:sticky lg:top-8 lg:rounded-[24px] lg:border lg:border-border lg:p-8 max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-10 max-lg:w-full max-lg:border-t max-lg:border-border max-lg:bg-background max-lg:px-[var(--gut)] max-lg:py-4"
    >
      <div class="text-[34px] leading-none tracking-[-0.03em] tabular-nums lg:text-[46px] lg:leading-[0.94]">{{ formatEuros(total) }}</div>

      <div v-if="!accepted" class="mt-6 border-t border-border pt-5 max-lg:hidden">
        <div
          v-for="row in breakdown"
          :key="row.label"
          class="flex justify-between gap-4 py-1 text-[13px]"
          :class="row.lead ? 'font-semibold' : 'text-muted-foreground'"
        >
          <span class="min-w-0 truncate">{{ row.label }}</span>
          <span class="whitespace-nowrap tabular-nums">{{
            row.amount === null ? $t('quotes.includedPrice') : formatEuros(row.amount)
          }}</span>
        </div>
      </div>

      <!-- Die erste Stufe trägt Farbe: sie ist die einzige, die mit der
           Zusage fällig wird. -->
      <div v-if="stages.length" class="mt-3 flex gap-4 lg:mt-6 lg:border-t lg:border-border lg:pt-5">
        <div v-for="(stage, index) in stages" :key="stage.label" class="min-w-0 flex-1">
          <div
            class="truncate text-[10px] leading-none font-semibold tracking-[0.11em] uppercase"
            :class="index === 0 ? 'text-[var(--accent)]' : 'text-muted-foreground'"
          >{{ stage.label }}</div>
          <div
            class="mt-1.5 text-[15px] leading-[1.2] font-semibold tabular-nums lg:text-[22px] lg:tracking-[-0.02em]"
            :class="index === 0 ? 'text-[var(--accent)]' : ''"
          >{{ formatEuros(stage.amount) }}</div>
        </div>
      </div>

      <div
        v-if="accepted"
        class="mt-3.5 flex w-full items-center gap-2.5 rounded-[14px] bg-ok-bg px-[18px] py-4 text-[14.5px] leading-[1.3] font-semibold text-ok"
      >
        <svg class="size-4" viewBox="0 0 24 24"><path d="M4 12.5l5 5L20 6.5" /></svg>
        {{ $t('portal.quoteAccepted', { date: acceptedAt }) }}
      </div>
      <template v-else>
        <p v-if="expired" class="mt-3.5 text-[13.5px] text-destructive">{{ $t('portal.quoteExpired') }}</p>
        <p v-if="rejection" class="mt-3.5 text-[13.5px] text-destructive">{{ rejection }}</p>

        <Button
          v-if="canAccept"
          class="mt-3.5 w-full rounded-[var(--r-pill)] py-[17px] text-[15px] font-semibold"
          variant="portal"
          type="button"
          :disabled="form.processing"
          @click="accept"
        >
          {{ $t('portal.quoteAccept') }}
        </Button>

        <p v-if="quote.validUntil" class="mt-4 text-[13px] text-muted-foreground max-lg:hidden">
          {{ $t('quotes.validUntil') }} {{ quote.validUntil }}
        </p>
      </template>
    </aside>
  </div>
</template>
