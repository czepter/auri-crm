<script setup lang="ts">
import { computed } from 'vue'
import type { StageRow } from './quotePayload'
import ValidUntilField from '@/admin/components/ValidUntilField.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'

const props = defineProps<{ quoteId: string }>()
const validUntil = defineModel<string>('validUntil', { required: true })
const stages = defineModel<StageRow[]>('stages', { required: true })
const emit = defineEmits<{ change: [] }>()

const percentSum = computed(() => stages.value.reduce((sum, stage) => sum + (stage.percent || 0), 0))

function addStage() {
  stages.value = [...stages.value, { percent: 0, label: '', minimum: '' }]
  emit('change')
}

function removeStage(index: number) {
  stages.value = stages.value.filter((_, i) => i !== index)
  emit('change')
}
</script>

<template>
  <div class="flex flex-col gap-5">
    <ValidUntilField :id="`terms-valid-${props.quoteId}`" v-model="validUntil" @blur.capture="emit('change')" />

    <div class="flex flex-col gap-2.5">
      <Label>{{ $t('quotes.payment') }}</Label>

      <div v-for="(stage, index) in stages" :key="index" class="flex flex-wrap items-center gap-3">
        <InputGroup class="w-24">
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
        <Input v-model="stage.label" class="w-[220px]" type="text" :placeholder="$t('quotes.stageLabel')" @blur="emit('change')" />
        <span class="text-[13px] text-ink-2">{{ $t('quotes.stageAtLeast') }}</span>
        <InputGroup class="w-28">
          <InputGroupInput v-model="stage.minimum" class="text-right tabular-nums" type="text" inputmode="decimal" @blur="emit('change')" />
          <InputGroupAddon align="inline-end">€</InputGroupAddon>
        </InputGroup>
        <Button variant="ghost" tone="accent" type="button" class="ml-auto" @click="removeStage(index)">{{ $t('common.remove') }}</Button>
      </div>

      <div class="mt-1 flex items-center gap-3">
        <Button variant="outline" type="button" @click="addStage">{{ $t('quotes.addStage') }}</Button>
        <span class="text-[13px] tabular-nums" :class="percentSum !== 100 ? 'font-semibold text-destructive' : 'text-ink-2'">
          {{ percentSum === 100 ? '100 %' : $t('quotes.stagePercentOff', { percent: percentSum }) }}
        </span>
      </div>
    </div>
  </div>
</template>
