<script setup lang="ts">
import { ref } from 'vue'
import { InputGroup, InputGroupInput } from '@/ui/input-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

// parseDiscount liest "10 %" als Prozent und alles andere als Euro.
const model = defineModel<string>({ required: true })
const emit = defineEmits<{ change: [] }>()

const unit = ref(model.value.trim().endsWith('%') ? '%' : '€')
const amount = ref(model.value.replace('%', '').trim())

function sync() {
  const value = String(amount.value).trim()
  model.value = value === '' ? '' : unit.value === '%' ? `${value} %` : value
}

function setUnit(value: unknown) {
  unit.value = String(value)
  sync()
  emit('change')
}
</script>

<template>
  <InputGroup>
    <InputGroupInput
      v-model="amount"
      class="text-right tabular-nums"
      type="text"
      inputmode="decimal"
      @update:model-value="sync"
      @blur="emit('change')"
    />
    <Select :model-value="unit" @update:model-value="setUnit">
      <SelectTrigger size="sm" class="w-auto gap-1 border-0 bg-transparent pr-[11px]">
        <SelectValue />
      </SelectTrigger>
      <SelectContent align="end" class="w-auto min-w-16">
        <SelectItem value="€">€</SelectItem>
        <SelectItem value="%">%</SelectItem>
      </SelectContent>
    </Select>
  </InputGroup>
</template>
