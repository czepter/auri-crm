<script setup lang="ts">
import { computed } from 'vue'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

const props = defineProps<{ modelValue: string; label: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const TIMES = Array.from({ length: 48 }, (_, i) => `${String(Math.floor(i / 2)).padStart(2, '0')}:${i % 2 ? '30' : '00'}`)

const day = computed(() => props.modelValue.slice(0, 10))
const time = computed(() => props.modelValue.slice(11, 16) || '09:00')

const set = (nextDay: string, nextTime: string) => emit('update:modelValue', nextDay === '' ? '' : `${nextDay}T${nextTime}`)
</script>

<template>
  <div class="flex items-center gap-2">
    <Input
      :model-value="day"
      class="w-auto"
      lang="de"
      type="date"
      :aria-label="`${label} (${$t('common.dateFormat')})`"
      :title="$t('common.dateFormat')"
      @update:model-value="(value) => set(String(value), time)"
    />
    <Select :model-value="time" :disabled="day === ''" @update:model-value="(value) => set(day, String(value))">
      <SelectTrigger class="w-[96px]" :aria-label="`${label} (${$t('common.time')})`"><SelectValue /></SelectTrigger>
      <SelectContent>
        <SelectItem v-for="option in TIMES" :key="option" :value="option">{{ option }}</SelectItem>
      </SelectContent>
    </Select>
  </div>
</template>
