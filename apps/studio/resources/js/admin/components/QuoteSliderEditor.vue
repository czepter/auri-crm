<script setup lang="ts">
/*
 * Q-14: die fünf Abschnitte, die ein Angebot mit Regler statt einer
 * Positionstabelle braucht. Ohne Stundensatz rendert Quotes.vue weiter
 * QuoteTable — die Weiche ist ein Feld, kein Schalter.
 *
 * Zwei Sorten Zeile, und zusammen sind sie alle: was ohne Aufpreis dabei ist,
 * und die Bausteine, die der Kunde schaltet. Eine feste Position mit eigenem
 * Preis hat hier keinen Platz — der Betrag ist Stunden mal Satz plus die
 * gewählten Bausteine —, und eine aus einem Paket geerbte wird beim Speichern
 * zu einer Zeile ohne Aufpreis.
 *
 * Die Spanne steht am Fuß, weil der Fotograf vor dem Senden sehen muss, was
 * der Regler zulässt: sonst verschickt er eine Untergrenze, die er nicht
 * bedienen will.
 */
import { computed } from 'vue'
import { formatEuros } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'

export type SliderRow = {
  id?: string
  title: string
  outcome: string
  unitPrice: string
  selectable: boolean
  selected: boolean
  hasImage: boolean
}

export type StageRow = { percent: number; label: string; minimum: string }

export type SliderDraft = {
  hourlyRate: string
  minHours: number | null
  maxHours: number | null
  imagesPerHour: number | null
  hours: number | null
  rows: SliderRow[]
  stages: StageRow[]
}

const draft = defineModel<SliderDraft>({ required: true })
const props = defineProps<{ quoteId: string }>()
const emit = defineEmits<{ change: [] }>()

const euros = (value: string) => Math.round(Number(String(value).replace(',', '.')) * 100) || 0
const ROW = 'mt-9 min-[1180px]:flex min-[1180px]:gap-6'
const LAB = 'mb-2.5 text-[15px] leading-[1.2] font-semibold tracking-[-0.02em] min-[1180px]:mb-0 min-[1180px]:w-28 min-[1180px]:flex-none min-[1180px]:text-[12.5px] min-[1180px]:tracking-[-0.012em] min-[1180px]:text-ink-2'

const rate = computed(() => euros(draft.value.hourlyRate))
const minHours = computed(() => draft.value.minHours ?? 0)
const maxHours = computed(() => draft.value.maxHours ?? 0)
const perHour = computed(() => draft.value.imagesPerHour ?? 0)

const included = computed(() => draft.value.rows.filter((row) => !row.selectable))
const blocks = computed(() => draft.value.rows.filter((row) => row.selectable))

const span = computed(() => {
  const hours = draft.value.hours ?? minHours.value
  const chosen = blocks.value.filter((row) => row.selected)

  return {
    min: minHours.value * rate.value,
    preselected: hours * rate.value + chosen.reduce((sum, row) => sum + euros(row.unitPrice), 0),
    max: maxHours.value * rate.value + blocks.value.reduce((sum, row) => sum + euros(row.unitPrice), 0),
    hours,
    chosen: chosen.length,
  }
})

/* Dieselbe Regel wie App\Payments\Support\PaymentStageSplitter — hier nur,
   um das Beispiel neben der Zeile zu rechnen. */
const amounts = computed(() => {
  let rest = span.value.preselected
  const last = draft.value.stages.length - 1

  return draft.value.stages.map((stage, index) => {
    const amount =
      index === last
        ? Math.max(0, rest)
        : Math.min(Math.max(share(stage.percent), euros(stage.minimum)), Math.max(0, rest))

    rest -= amount

    return amount
  })
})

function share(percent: number) {
  return Math.round((span.value.preselected * (percent || 0)) / 100 / 1000) * 1000
}

const percentSum = computed(() => draft.value.stages.reduce((sum, stage) => sum + (stage.percent || 0), 0))
const minimumSum = computed(() => draft.value.stages.reduce((sum, stage) => sum + euros(stage.minimum), 0))
// Nur die Anteilssumme ist ein Fehler. Mindestbeträge über dem kleinsten
// Angebot sind keiner: PaymentStageSplitter lässt die späteren Stufen dann
// entfallen — bewusst und getestet. Die Zeile darunter sagt die Folge, statt
// eine Konfiguration zu bemängeln, die das Studio so gewollt haben kann.
const stagesInvalid = computed(() => percentSum.value !== 100)

function addRow(selectable: boolean) {
  draft.value.rows = [
    ...draft.value.rows,
    { title: '', outcome: '', unitPrice: '', selectable, selected: false, hasImage: false },
  ]
  emit('change')
}

function removeRow(row: SliderRow) {
  draft.value.rows = draft.value.rows.filter((entry) => entry !== row)
  emit('change')
}

function addStage() {
  draft.value.stages = [...draft.value.stages, { percent: 0, label: '', minimum: '' }]
  emit('change')
}

function removeStage(index: number) {
  draft.value.stages = draft.value.stages.filter((_, i) => i !== index)
  emit('change')
}

const GRID = 'grid grid-cols-[44px_minmax(0,1fr)_200px_100px_130px] items-center gap-4 max-[900px]:grid-cols-[44px_minmax(0,1fr)_110px]'
</script>

<template>
  <div>
    <div :class="ROW">
      <div :class="LAB">{{ $t('quotes.sliderTime') }}</div>
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-end gap-5">
          <label class="block">
            <span class="mb-[7px] block text-[12.5px] leading-[1.2] font-semibold tracking-[-0.012em] text-ink-2">{{ $t('quotes.hourlyRate') }}</span>
            <Input v-model="draft.hourlyRate" class="w-24 tabular-nums" type="text" inputmode="decimal" @blur="emit('change')" />
          </label>
          <label class="block">
            <span class="mb-[7px] block text-[12.5px] leading-[1.2] font-semibold tracking-[-0.012em] text-ink-2">{{ $t('quotes.minHours') }}</span>
            <Input
              class="w-[72px] tabular-nums"
              type="number"
              min="0"
              :model-value="draft.minHours ?? ''"
              @update:model-value="(value) => (draft.minHours = value === '' ? null : Number(value))"
              @blur="emit('change')"
            />
          </label>
          <label class="block">
            <span class="mb-[7px] block text-[12.5px] leading-[1.2] font-semibold tracking-[-0.012em] text-ink-2">{{ $t('quotes.maxHours') }}</span>
            <Input
              class="w-[72px] tabular-nums"
              type="number"
              min="0"
              :model-value="draft.maxHours ?? ''"
              @update:model-value="(value) => (draft.maxHours = value === '' ? null : Number(value))"
              @blur="emit('change')"
            />
          </label>
          <label class="block">
            <span class="mb-[7px] block text-[12.5px] leading-[1.2] font-semibold tracking-[-0.012em] text-ink-2">{{ $t('quotes.imagesPerHour') }}</span>
            <Input
              class="w-24 tabular-nums"
              type="number"
              min="0"
              :model-value="draft.imagesPerHour ?? ''"
              @update:model-value="(value) => (draft.imagesPerHour = value === '' ? null : Number(value))"
              @blur="emit('change')"
            />
          </label>

          <!-- Was die Enden kosten, sofort neben den Feldern. -->
          <div class="pb-2 text-[13px] text-ink-2 tabular-nums">
            {{
              $t('quotes.sliderEnds', {
                minHours,
                minPrice: formatEuros(span.min),
                maxHours,
                maxPrice: formatEuros(maxHours * rate),
                minImages: perHour * minHours,
                maxImages: perHour * maxHours,
              })
            }}
          </div>
        </div>
      </div>
    </div>

    <div :class="ROW">
      <div :class="LAB">{{ $t('quotes.sliderIncluded') }}</div>
      <div class="min-w-0 flex-1">
        <!-- Die erste rechnet mit dem Regler mit und ist deshalb keine Zeile,
             die jemand schreibt. -->
        <div class="flex items-center gap-3 border-t border-rule py-[9px] text-ink-2">
          <span>{{ $t('quotes.derivedImages', { min: perHour * minHours, max: perHour * maxHours }) }}</span>
          <span class="ml-auto rounded-[12px] bg-sunk-2 px-[9px] py-1 text-[11px] leading-none font-semibold text-ink-2">
            {{ $t('quotes.automatic') }}
          </span>
        </div>

        <div v-for="row in included" :key="row.id ?? included.indexOf(row)" class="flex items-center gap-3 border-t border-rule py-[9px]">
          <Input v-model="row.title" class="flex-1" type="text" :placeholder="$t('quotes.itemTitle')" @blur="emit('change')" />
          <button class="cursor-pointer text-[13px] text-ink-2" type="button" :aria-label="$t('common.remove')" @click="removeRow(row)">×</button>
        </div>

        <Button class="mt-3" variant="outline" type="button" @click="addRow(false)">
          <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
          {{ $t('quotes.addIncluded') }}
        </Button>
      </div>
    </div>

    <div :class="ROW">
      <div :class="LAB">{{ $t('quotes.sliderBlocks') }}</div>
      <div class="min-w-0 flex-1">
        <div :class="[GRID, 'pb-2 text-[11.5px] leading-none font-medium text-ink-2']">
          <span>{{ $t('quotes.blockImage') }}</span>
          <span>{{ $t('quotes.itemTitle') }}</span>
          <span class="max-[900px]:hidden">{{ $t('quotes.itemOutcome') }}</span>
          <span class="text-right max-[900px]:hidden">{{ $t('quotes.itemPrice') }}</span>
          <span class="max-[900px]:hidden">{{ $t('quotes.inPortal') }}</span>
        </div>

        <div v-for="row in blocks" :key="row.id ?? blocks.indexOf(row)" :class="[GRID, 'border-t border-rule py-2.5']">
          <!-- Anzeige, kein Uploadfeld: gepflegt wird das Bild am
               Katalogeintrag, und wer es hier anklickt, landet dort. -->
          <img
            v-if="row.hasImage && row.id"
            class="h-8 w-11 rounded-[6px] object-cover"
            :src="`/angebote/${props.quoteId}/positionen/${row.id}/bild`"
            alt=""
          />
          <span v-else class="h-8 w-11 rounded-[6px] bg-sunk-2"></span>

          <Input v-model="row.title" type="text" :placeholder="$t('quotes.itemTitle')" @blur="emit('change')" />
          <Input v-model="row.outcome" class="max-[900px]:hidden" type="text" :placeholder="$t('quotes.itemOutcome')" @blur="emit('change')" />
          <Input v-model="row.unitPrice" class="text-right tabular-nums max-[900px]:hidden" type="text" inputmode="decimal" @blur="emit('change')" />

          <div class="flex items-center gap-2">
            <button
              class="inline-flex cursor-pointer rounded-[12px] px-2.5 py-[5px] text-[11.5px] leading-none"
              :class="row.selected ? 'bg-[var(--accent-bg)] font-semibold text-[var(--accent)]' : 'border border-border text-ink-2'"
              type="button"
              @click="row.selected = !row.selected; emit('change')"
            >{{ row.selected ? $t('quotes.preselected') : $t('quotes.pickable') }}</button>
            <button class="ml-auto cursor-pointer text-[13px] text-ink-2" type="button" :aria-label="$t('common.remove')" @click="removeRow(row)">×</button>
          </div>
        </div>

        <Button class="mt-3" variant="outline" type="button" @click="addRow(true)">
          <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
          {{ $t('quotes.addBlock') }}
        </Button>
      </div>
    </div>

    <div :class="ROW">
      <div :class="LAB">{{ $t('quotes.sliderPayment') }}</div>
      <div class="min-w-0 flex-1">
        <div v-for="(stage, index) in draft.stages" :key="index" class="mb-2.5 flex flex-wrap items-center gap-3">
          <Input
            class="w-[72px] text-right tabular-nums"
            type="number"
            min="0"
            max="100"
            :model-value="stage.percent"
            @update:model-value="(value) => (stage.percent = Number(value) || 0)"
            @blur="emit('change')"
          />
          <span class="text-[13px] text-ink-2">%</span>
          <Input v-model="stage.label" class="w-[230px]" type="text" :placeholder="$t('quotes.stageLabel')" @blur="emit('change')" />
          <span class="text-[13px] text-ink-2">{{ $t('quotes.stageAtLeast') }}</span>
          <Input v-model="stage.minimum" class="w-[88px] text-right tabular-nums" type="text" inputmode="decimal" @blur="emit('change')" />
          <!-- Farbig, sobald der Mindestbetrag den Prozentsatz überstimmt. -->
          <span
            class="text-[13px] tabular-nums"
            :class="amounts[index] > share(stage.percent) ? 'font-semibold text-[var(--accent)]' : 'text-ink-2'"
          >{{ formatEuros(amounts[index]) }}</span>
          <button class="ml-auto cursor-pointer text-[13px] text-ink-2" type="button" :aria-label="$t('common.remove')" @click="removeStage(index)">×</button>
        </div>

        <div class="mt-3.5 flex flex-wrap items-center gap-4">
          <Button variant="outline" type="button" @click="addStage">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            {{ $t('quotes.addStage') }}
          </Button>
          <span class="text-[13px] tabular-nums" :class="stagesInvalid ? 'font-semibold text-destructive' : 'text-ink-2'">
            <template v-if="percentSum !== 100">{{ $t('quotes.stagePercentOff', { percent: percentSum }) }}</template>
            <template v-else-if="minimumSum > span.min">{{ $t('quotes.stageMinimumsCollapse', { amount: formatEuros(minimumSum) }) }}</template>
            <template v-else>100 %</template>
          </span>
        </div>
      </div>
    </div>

    <div :class="ROW">
      <div :class="LAB">{{ $t('quotes.sliderSpan') }}</div>
      <div class="flex min-w-0 flex-1 gap-3 max-[900px]:flex-col">
        <div class="flex-1 rounded-[20px] bg-sunk p-5">
          <div class="text-[11.5px] leading-none font-medium text-ink-2">{{ $t('quotes.spanMin') }}</div>
          <div class="mt-2.5 text-[22px] leading-none font-semibold tracking-[-0.03em] tabular-nums">{{ formatEuros(span.min) }}</div>
          <div class="mt-1.5 text-[13px] text-ink-2">{{ $t('quotes.spanMinNote', { hours: minHours }) }}</div>
        </div>
        <div class="flex-1 rounded-[20px] bg-[var(--accent-bg)] p-5 text-[var(--accent)]">
          <div class="text-[11.5px] leading-none font-medium">{{ $t('quotes.spanPreselected') }}</div>
          <div class="mt-2.5 text-[22px] leading-none font-semibold tracking-[-0.03em] tabular-nums">{{ formatEuros(span.preselected) }}</div>
          <div class="mt-1.5 text-[13px]">{{ $t('quotes.spanPreselectedNote', { hours: span.hours, blocks: span.chosen }) }}</div>
        </div>
        <div class="flex-1 rounded-[20px] bg-sunk p-5">
          <div class="text-[11.5px] leading-none font-medium text-ink-2">{{ $t('quotes.spanMax') }}</div>
          <div class="mt-2.5 text-[22px] leading-none font-semibold tracking-[-0.03em] tabular-nums">{{ formatEuros(span.max) }}</div>
          <div class="mt-1.5 text-[13px] text-ink-2">{{ $t('quotes.spanMaxNote', { hours: maxHours }) }}</div>
        </div>
      </div>
    </div>
  </div>
</template>
