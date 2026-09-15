<script setup lang="ts">
/*
 * #412. Die beiden Sätze, die ein Fotograf tatsächlich abrechnet — 19 % auf
 * die Leistung, 7 % auf das gedruckte Buch (§ 12 Abs. 2 Nr. 1 UStG, Anlage 2
 * Nr. 49). Leer heisst der Satz des Angebots und ist der Normalfall.
 */
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

const model = defineModel<number | null>({ required: true })
const props = defineProps<{ id?: string; placeholder: string }>()

function set(value: unknown) {
  model.value = value === '' || value === null ? null : Number(value)
}
</script>

<template>
  <Select :model-value="model === null ? '' : String(model)" @update:model-value="set">
    <SelectTrigger :id="props.id">
      <SelectValue :placeholder="props.placeholder" />
    </SelectTrigger>
    <SelectContent>
      <SelectItem value="">{{ props.placeholder }}</SelectItem>
      <SelectItem value="1900">19 %</SelectItem>
      <SelectItem value="700">7 %</SelectItem>
    </SelectContent>
  </Select>
</template>
