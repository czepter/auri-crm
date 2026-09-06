<script setup lang="ts">
/*
 * Q-14: die fünf Abschnitte, die ein Angebot mit Regler statt einer
 * Positionstabelle braucht. Ohne Stundensatz rendert Quotes.vue weiter
 * QuoteTable — die Weiche ist ein Feld, kein Schalter.
 *
 * Die Spanne steht hier, weil der Fotograf vor dem Senden sehen muss, was
 * der Regler zulässt: sonst verschickt er eine Untergrenze, die er nicht
 * bedienen will.
 */
import { computed } from 'vue'
import { formatEuros } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import { Switch } from '@/ui/switch'

export type SliderRow = {
  title: string
  outcome: string
  unitPrice: string
  id?: string
  selectable: boolean
  selected: boolean
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
const emit = defineEmits<{ change: [] }>()

const euros = (value: string) => Math.round(Number(String(value).replace(',', '.')) * 100) || 0

const rate = computed(() => euros(draft.value.hourlyRate))
const included = computed(() => draft.value.rows.filter((row) => row.unitPrice === '' && !row.selectable))
const blocks = computed(() => draft.value.rows.filter((row) => row.selectable))
const fixed = computed(() => draft.value.rows.filter((row) => row.unitPrice !== '' && !row.selectable))

const fixedTotal = computed(() => fixed.value.reduce((sum, row) => sum + euros(row.unitPrice), 0))
const blockTotal = computed(() => blocks.value.reduce((sum, row) => sum + euros(row.unitPrice), 0))

const span = computed(() => ({
  min: (draft.value.minHours ?? 0) * rate.value + fixedTotal.value,
  preselected:
    (draft.value.hours ?? draft.value.minHours ?? 0) * rate.value +
    fixedTotal.value +
    blocks.value.filter((row) => row.selected).reduce((sum, row) => sum + euros(row.unitPrice), 0),
  max: (draft.value.maxHours ?? 0) * rate.value + fixedTotal.value + blockTotal.value,
}))

const percentSum = computed(() => draft.value.stages.reduce((sum, stage) => sum + (stage.percent || 0), 0))
const minimumSum = computed(() => draft.value.stages.reduce((sum, stage) => sum + euros(stage.minimum), 0))
const stagesInvalid = computed(() => percentSum.value !== 100 || minimumSum.value > span.value.min)

function addRow(selectable: boolean) {
  draft.value.rows = [
    ...draft.value.rows,
    { title: '', outcome: '', unitPrice: '', selectable, selected: false },
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
</script>

<template>
  <div>
    <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">
      {{ $t('quotes.sliderTime') }}
    </div>
    <div class="mb-6 grid grid-cols-4 gap-x-5 gap-y-4 max-[720px]:grid-cols-2">
      <Field v-slot="{ id }" :label="$t('quotes.hourlyRate')">
        <InputGroup>
          <InputGroupInput :id="id" v-model="draft.hourlyRate" class="tabular-nums" type="text" inputmode="decimal" @blur="emit('change')" />
          <InputGroupAddon align="inline-end">€</InputGroupAddon>
        </InputGroup>
      </Field>
      <Field v-slot="{ id }" :label="$t('quotes.minHours')">
        <Input :id="id" :model-value="draft.minHours ?? ''"
          @update:model-value="(value) => (draft.minHours = value === '' ? null : Number(value))" class="tabular-nums" type="number" min="0" @blur="emit('change')" />
      </Field>
      <Field v-slot="{ id }" :label="$t('quotes.maxHours')">
        <Input :id="id" :model-value="draft.maxHours ?? ''"
          @update:model-value="(value) => (draft.maxHours = value === '' ? null : Number(value))" class="tabular-nums" type="number" min="0" @blur="emit('change')" />
      </Field>
      <Field v-slot="{ id }" :label="$t('quotes.imagesPerHour')">
        <Input :id="id" :model-value="draft.imagesPerHour ?? ''"
          @update:model-value="(value) => (draft.imagesPerHour = value === '' ? null : Number(value))" class="tabular-nums" type="number" min="0" @blur="emit('change')" />
      </Field>
    </div>

    <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">
      {{ $t('quotes.sliderIncluded') }}
    </div>
    <div class="mb-6">
      <div v-for="row in included" :key="row.title + included.indexOf(row)" class="mb-2 flex items-center gap-3">
        <Input v-model="row.title" class="flex-1" type="text" :placeholder="$t('quotes.itemTitle')" @blur="emit('change')" />
        <Input v-model="row.outcome" class="flex-1" type="text" :placeholder="$t('quotes.itemOutcome')" @blur="emit('change')" />
        <button class="cursor-pointer text-[12.5px] text-ink-2" type="button" @click="removeRow(row)">×</button>
      </div>
      <Button variant="outline" type="button" @click="addRow(false)">{{ $t('quotes.addIncluded') }}</Button>
    </div>

    <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">
      {{ $t('quotes.sliderBlocks') }}
    </div>
    <div class="mb-6">
      <div v-for="row in blocks" :key="row.title + blocks.indexOf(row)" class="mb-3 flex flex-wrap items-center gap-3">
        <!-- Das Bild ist hier Anzeige: gepflegt wird es am Katalogeintrag. -->
        <span class="size-10 flex-none rounded-[6px] bg-sunk-2"></span>
        <Input v-model="row.title" class="min-w-[140px] flex-1" type="text" :placeholder="$t('quotes.itemTitle')" @blur="emit('change')" />
        <Input v-model="row.outcome" class="min-w-[140px] flex-1" type="text" :placeholder="$t('quotes.itemOutcome')" @blur="emit('change')" />
        <InputGroup class="w-28">
          <InputGroupInput v-model="row.unitPrice" class="tabular-nums" type="text" inputmode="decimal" @blur="emit('change')" />
          <InputGroupAddon align="inline-end">€</InputGroupAddon>
        </InputGroup>
        <div class="flex items-center gap-2">
          <Switch :id="`sel-${blocks.indexOf(row)}`" v-model="row.selected" @update:model-value="emit('change')" />
          <Label variant="inline" :for="`sel-${blocks.indexOf(row)}`">{{ $t('quotes.preselected') }}</Label>
        </div>
        <button class="cursor-pointer text-[12.5px] text-ink-2" type="button" @click="removeRow(row)">×</button>
      </div>
      <Button variant="outline" type="button" @click="addRow(true)">{{ $t('quotes.addBlock') }}</Button>
    </div>

    <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">
      {{ $t('quotes.sliderPayment') }}
    </div>
    <div class="mb-6">
      <div v-for="(stage, index) in draft.stages" :key="index" class="mb-2 flex flex-wrap items-center gap-3">
        <InputGroup class="w-24">
          <InputGroupInput v-model.number="stage.percent" class="tabular-nums" type="number" min="0" max="100" @blur="emit('change')" />
          <InputGroupAddon align="inline-end">%</InputGroupAddon>
        </InputGroup>
        <Input v-model="stage.label" class="min-w-[180px] flex-1" type="text" :placeholder="$t('quotes.stageLabel')" @blur="emit('change')" />
        <InputGroup class="w-32">
          <InputGroupInput v-model="stage.minimum" class="tabular-nums" type="text" inputmode="decimal" :placeholder="$t('quotes.stageMinimum')" @blur="emit('change')" />
          <InputGroupAddon align="inline-end">€</InputGroupAddon>
        </InputGroup>
        <button class="cursor-pointer text-[12.5px] text-ink-2" type="button" @click="removeStage(index)">×</button>
      </div>

      <div class="flex items-center gap-4">
        <Button variant="outline" type="button" @click="addStage">{{ $t('quotes.addStage') }}</Button>
        <span v-if="draft.stages.length" class="text-[12.5px] tabular-nums" :class="stagesInvalid ? 'text-destructive' : 'text-ink-2'">
          {{ $t('quotes.stageSum', { percent: percentSum, minimum: formatEuros(minimumSum) }) }}
        </span>
      </div>
    </div>

    <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">
      {{ $t('quotes.sliderSpan') }}
    </div>
    <div class="flex flex-wrap gap-8 text-[13.5px] tabular-nums">
      <span>{{ $t('quotes.spanMin') }} <strong>{{ formatEuros(span.min) }}</strong></span>
      <span>{{ $t('quotes.spanPreselected') }} <strong>{{ formatEuros(span.preselected) }}</strong></span>
      <span>{{ $t('quotes.spanMax') }} <strong>{{ formatEuros(span.max) }}</strong></span>
    </div>
  </div>
</template>
