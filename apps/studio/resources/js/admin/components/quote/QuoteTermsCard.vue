<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import type { StageRow } from './quotePayload'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'

const { t } = useI18n()
const stages = defineModel<StageRow[]>('stages', { required: true })
const emit = defineEmits<{ change: [] }>()

const nameSuggestions = [t('quotes.stageNameDeposit'), t('quotes.stageNameReservation'), t('quotes.stageNameInstallment')]

const percentSum = computed(() => stages.value.reduce((sum, stage) => sum + (stage.fixed ? 0 : stage.percent || 0), 0))
const anyFixed = computed(() => stages.value.some((stage) => stage.fixed))
const UNITS = [{ value: '%', label: '%' }, { value: '€', label: '€' }]

function setUnit(stage: StageRow, unit: string) {
  stage.fixed = unit === '€'
  emit('change')
}

// Nie leer: eine Stufe mit 100 % ist die Zahlung "alles bei Zusage", nicht
// "keine geplant" — eine leere Liste ist nur ein Angebot, das das noch nie
// bediente.
onMounted(() => {
  if (stages.value.length === 0) {
    stages.value = [{ percent: 100, label: t('quotes.stageDefaultLabel'), minimum: '', note: '', dueOn: '', fixed: false }]
    emit('change')
  }
})

function addStage() {
  stages.value = [...stages.value, { percent: 0, label: '', minimum: '', note: '', dueOn: '', fixed: false }]
  emit('change')
}

function removeStage(index: number) {
  stages.value = stages.value.length > 1
    ? stages.value.filter((_, i) => i !== index)
    : [{ percent: 100, label: t('quotes.stageDefaultLabel'), minimum: '', note: '', dueOn: '', fixed: false }]
  emit('change')
}
</script>

<template>
  <div class="flex flex-col gap-2.5">
    <datalist id="stage-names"><option v-for="name in nameSuggestions" :key="name" :value="name" /></datalist>

    <div v-for="(stage, index) in stages" :key="index" class="flex flex-wrap items-center gap-3">
      <ChoiceGroup :model-value="stage.fixed ? '€' : '%'" :options="UNITS" :aria-label="$t('quotes.stageUnit')" @update:model-value="(unit) => setUnit(stage, unit)" />
      <InputGroup v-if="!stage.fixed" class="w-24">
        <InputGroupInput
          class="text-right tabular-nums"
          type="number"
          min="0"
          max="100"
          :model-value="stage.percent"
          @update:model-value="(value) => (stage.percent = Number(value) || 0)"
          @blur="emit('change')"
        />
        <InputGroupAddon align="inline-end">%</InputGroupAddon>
      </InputGroup>
      <InputGroup v-else class="w-28">
        <InputGroupInput v-model="stage.minimum" class="text-right tabular-nums" type="text" inputmode="decimal" :aria-label="$t('quotes.stageAmount')" @blur="emit('change')" />
        <InputGroupAddon align="inline-end">€</InputGroupAddon>
      </InputGroup>
      <Input :aria-label="$t('quotes.stageLabel')" v-model="stage.label" class="w-[220px]" type="text" list="stage-names" :placeholder="$t('quotes.stageLabel')" @blur="emit('change')" />
      <template v-if="!stage.fixed">
        <span class="text-small text-ink-2">{{ $t('quotes.stageAtLeast') }}</span>
        <InputGroup class="w-28">
          <InputGroupInput v-model="stage.minimum" class="text-right tabular-nums" type="text" inputmode="decimal" @blur="emit('change')" />
          <InputGroupAddon align="inline-end">€</InputGroupAddon>
        </InputGroup>
      </template>
      <Button variant="ghost" tone="accent" type="button" class="ml-auto" @click="removeStage(index)">{{ $t('common.remove') }}</Button>
      <Input :aria-label="$t('quotes.stageNote')" v-model="stage.note" class="min-w-[220px] flex-1" type="text" :placeholder="$t('quotes.stageNote')" @blur="emit('change')" />
      <Input :aria-label="$t('quotes.stageDue')" v-model="stage.dueOn" class="w-40" type="date" @change="emit('change')" />
    </div>

    <div class="mt-1 flex items-center gap-3">
      <Button variant="outline" type="button" @click="addStage">{{ $t('quotes.addStage') }}</Button>
      <span v-if="!anyFixed" class="text-small tabular-nums" :class="percentSum !== 100 ? 'font-semibold text-destructive' : 'text-ink-2'">
        {{ percentSum === 100 ? '100 %' : $t('quotes.stagePercentOff', { percent: percentSum }) }}
      </span>
    </div>
  </div>
</template>
