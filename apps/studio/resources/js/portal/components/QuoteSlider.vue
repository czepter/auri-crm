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

// Die Aufstellung sagt, was der Betrag deckt, in der Reihenfolge, in der er
// entsteht: die Stunden, dann was ohne Aufpreis folgt, dann jeder gewählte
// Baustein.
const breakdown = computed(() => [
  { label: `${hours.value} ${hours.value === 1 ? 'Stunde' : 'Stunden'}`, amount: hours.value * (props.quote.hourlyRate ?? 0), lead: true },
  ...fixed.value.map((item) => ({ label: item.title, amount: item.lineTotal, lead: false })),
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
    <div class="min-w-0 flex-1 max-lg:w-full">
      <div class="flex items-baseline justify-between gap-4">
        <span class="font-serif text-[clamp(40px,7vw,64px)] leading-[1] tabular-nums">{{ hours }}</span>
        <span class="text-[13px] text-muted-foreground tabular-nums">
          {{ $t('quotes.imageCount', { count: images }) }}
        </span>
      </div>

      <!-- Alle 24 bleiben stehen und blenden zurück: das Wachsen ist eine
           Blende, kein Umbau. Ohne Schaufensterbilder entfällt das Raster. -->
      <div v-if="showcaseCount > 0" class="mt-6 grid grid-cols-12 gap-1">
        <div
          v-for="index in TILES"
          :key="index"
          class="aspect-square overflow-hidden rounded-[3px] bg-muted transition-opacity duration-300"
          :style="{ opacity: index <= litTiles ? 1 : 0.1 }"
        >
          <img class="size-full object-cover" :src="tileImage(index - 1)!" alt="" loading="lazy" />
        </div>
      </div>

      <input
        v-model.number="hours"
        class="mt-6 w-full accent-[var(--accent)]"
        type="range"
        :min="quote.minHours ?? 0"
        :max="quote.maxHours ?? 0"
        step="1"
        :disabled="accepted"
        :aria-label="$t('quotes.hours')"
      />

      <p class="mt-3 text-[13.5px] text-muted-foreground">
        {{ $t('quotes.perHour', { amount: formatEuros(quote.hourlyRate ?? 0) }) }}
      </p>

      <!-- Auf dem Desktop trägt das Rail die Aufstellung; die Chips hier
           sagten dann dasselbe ein zweites Mal. -->
      <div v-if="included.length" class="mt-8 flex flex-wrap gap-2 lg:hidden">
        <span
          v-for="item in included"
          :key="item.id"
          class="rounded-[var(--r-pill)] bg-muted px-3 py-1.5 text-[12.5px] leading-none"
        >{{ item.outcome ?? item.title }}</span>
      </div>

      <div v-if="blocks.length" class="mt-10 grid grid-cols-2 gap-3">
        <button
          v-for="item in blocks"
          :key="item.id"
          class="overflow-hidden rounded-[10px] border text-left transition-[filter,box-shadow] duration-200"
          :class="
            chosen.has(item.id)
              ? 'border-[var(--accent)] shadow-[0_0_0_1px_var(--accent)]'
              : 'border-border [filter:grayscale(1)] opacity-80'
          "
          :type="'button'"
          :aria-pressed="chosen.has(item.id)"
          :disabled="accepted"
          @click="toggle(item)"
        >
          <img
            v-if="item.hasImage"
            class="aspect-[4/3] w-full object-cover"
            :src="`/projekte/${project.id}/angebot/bild/${item.id}`"
            alt=""
            loading="lazy"
          />
          <span class="block p-3">
            <span class="block text-[14px] font-medium">{{ item.title }}</span>
            <span v-if="item.outcome" class="block text-[12.5px] text-muted-foreground">{{ item.outcome }}</span>
            <span class="mt-1 block text-[13px] tabular-nums">{{ formatEuros(item.lineTotal) }}</span>
          </span>
        </button>
      </div>
    </div>

    <!-- Desktop: klebendes Rail. Handy: klebende Leiste am Fuß. -->
    <aside
      class="w-[300px] flex-none lg:sticky lg:top-8 max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-10 max-lg:w-full max-lg:border-t max-lg:border-border max-lg:bg-background max-lg:px-[var(--gut)] max-lg:py-4"
    >
      <div class="flex items-baseline justify-between gap-4">
        <span class="text-[13px] text-muted-foreground">{{ $t('quotes.total') }}</span>
        <span class="font-serif text-[26px] leading-none tabular-nums">{{ formatEuros(total) }}</span>
      </div>

      <div v-if="!accepted" class="mt-4 max-lg:hidden">
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

      <div v-if="stages.length" class="mt-4 grid grid-cols-3 gap-2">
        <div v-for="(stage, index) in stages" :key="stage.label">
          <div
            class="text-[10px] leading-[1.3] font-semibold tracking-[0.08em] uppercase"
            :class="index === 0 ? 'text-[var(--accent)]' : 'text-muted-foreground'"
          >{{ stage.label }}</div>
          <div class="text-[13.5px] tabular-nums">{{ formatEuros(stage.amount) }}</div>
        </div>
      </div>

      <p v-if="accepted" class="mt-4 text-[13.5px] text-muted-foreground">
        {{ $t('portal.quoteAccepted', { date: acceptedAt }) }}
      </p>
      <template v-else>
        <p v-if="expired" class="mt-4 text-[13.5px] text-destructive">{{ $t('portal.quoteExpired') }}</p>
        <p v-if="rejection" class="mt-4 text-[13.5px] text-destructive">{{ rejection }}</p>

        <Button
          v-if="canAccept"
          class="mt-4 w-full"
          variant="portal"
          type="button"
          :disabled="form.processing"
          @click="accept"
        >
          {{ $t('portal.quoteAccept') }}
        </Button>
      </template>
    </aside>
  </div>
</template>
