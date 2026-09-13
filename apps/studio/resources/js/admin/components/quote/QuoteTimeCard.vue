<script setup lang="ts">
import { computed, useId } from 'vue'
import type { SliderDraft } from './quotePayload'
import { eurosToCents } from '@/admin/lib/money'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'

const timeTitle = defineModel<string | null>('timeTitle', { required: true })
const timeDescription = defineModel<string | null>('timeDescription', { required: true })
const slider = defineModel<SliderDraft>('slider', { required: true })
const emit = defineEmits<{ change: [] }>()

const uid = useId()

function numberField(key: 'minHours' | 'maxHours' | 'hours' | 'imagesPerHour') {
  return computed({
    get: () => slider.value[key] ?? '',
    set: (value: string) => (slider.value[key] = value === '' ? null : Number(value)),
  })
}

const minHours = numberField('minHours')
const maxHours = numberField('maxHours')
const hours = numberField('hours')
const imagesPerHour = numberField('imagesPerHour')

const titleField = computed({ get: () => timeTitle.value ?? '', set: (value: string) => (timeTitle.value = value === '' ? null : value) })
const descriptionField = computed({
  get: () => timeDescription.value ?? '',
  set: (value: string) => (timeDescription.value = value === '' ? null : value),
})

const rate = computed(() => eurosToCents(slider.value.hourlyRate) ?? 0)

const rateInvalid = computed(() => slider.value.hourlyRate !== '' && rate.value <= 0)
const minHoursInvalid = computed(() => slider.value.minHours !== null && slider.value.minHours < 1)
const maxBelowMin = computed(
  () => slider.value.minHours !== null && slider.value.maxHours !== null && slider.value.maxHours < slider.value.minHours,
)
const hoursOutOfRange = computed(
  () =>
    slider.value.hours !== null &&
    slider.value.minHours !== null &&
    slider.value.maxHours !== null &&
    (slider.value.hours < slider.value.minHours || slider.value.hours > slider.value.maxHours),
)
const imagesPerHourInvalid = computed(() => slider.value.imagesPerHour !== null && slider.value.imagesPerHour < 1)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex min-w-0 flex-col gap-1.5">
      <Label :for="`time-title-${uid}`">{{ $t('quotes.timeTitle') }}</Label>
      <Input :id="`time-title-${uid}`" v-model="titleField" type="text" @blur="emit('change')" />
    </div>

    <div class="flex min-w-0 flex-col gap-1.5">
      <Label :for="`time-description-${uid}`">{{ $t('quotes.timeDescription') }}</Label>
      <Textarea :id="`time-description-${uid}`" v-model="descriptionField" rows="2" @blur="emit('change')" />
    </div>

    <div class="grid grid-cols-5 gap-4">
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('quotes.hourlyRate') }}</Label>
        <InputGroup>
          <InputGroupInput v-model="slider.hourlyRate" class="tabular-nums" type="text" inputmode="decimal" @blur="emit('change')" />
          <InputGroupAddon align="inline-end">€</InputGroupAddon>
        </InputGroup>
        <p v-if="rateInvalid" class="text-[11.5px] text-destructive">{{ $t('quotes.rateInvalid') }}</p>
      </div>
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('quotes.minHours') }}</Label>
        <InputGroup>
          <InputGroupInput v-model="minHours" class="tabular-nums" type="number" min="0" @blur="emit('change')" />
          <InputGroupAddon align="inline-end">{{ $t('quotes.hours') }}</InputGroupAddon>
        </InputGroup>
        <p v-if="minHoursInvalid" class="text-[11.5px] text-destructive">{{ $t('quotes.minHoursInvalid') }}</p>
      </div>
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('quotes.maxHours') }}</Label>
        <InputGroup>
          <InputGroupInput v-model="maxHours" class="tabular-nums" type="number" min="0" @blur="emit('change')" />
          <InputGroupAddon align="inline-end">{{ $t('quotes.hours') }}</InputGroupAddon>
        </InputGroup>
        <p v-if="maxBelowMin" class="text-[11.5px] text-destructive">{{ $t('quotes.maxBelowMin') }}</p>
      </div>
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('quotes.spanPreselected') }}</Label>
        <InputGroup>
          <InputGroupInput v-model="hours" class="tabular-nums" type="number" min="0" @blur="emit('change')" />
          <InputGroupAddon align="inline-end">{{ $t('quotes.hours') }}</InputGroupAddon>
        </InputGroup>
        <p v-if="hoursOutOfRange" class="text-[11.5px] text-destructive">{{ $t('quotes.hoursOutOfRange') }}</p>
      </div>
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('quotes.imagesPerHour') }}</Label>
        <InputGroup>
          <InputGroupInput v-model="imagesPerHour" class="tabular-nums" type="number" min="0" @blur="emit('change')" />
          <InputGroupAddon align="inline-end">{{ $t('quotes.imagesUnit') }}</InputGroupAddon>
        </InputGroup>
        <p v-if="imagesPerHourInvalid" class="text-[11.5px] text-destructive">{{ $t('quotes.imagesPerHourInvalid') }}</p>
      </div>
    </div>
  </div>
</template>
