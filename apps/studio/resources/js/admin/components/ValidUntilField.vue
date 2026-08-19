<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { relativeDate, shiftDate } from '@/admin/lib/dates'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Label } from '@/ui/label'

defineProps<{ id: string }>()
const model = defineModel<string>({ required: true })

const { locale } = useI18n()

// Die Fristen, die ein Angebot in der Praxis bekommt — das Datumsfeld selbst
// bleibt daneben stehen, die Knöpfe füllen es nur.
const PRESETS = [
  { key: 'quotes.presetWeeks', count: 1, span: { weeks: 1 } },
  { key: 'quotes.presetWeeks', count: 2, span: { weeks: 2 } },
  { key: 'quotes.presetMonths', count: 1, span: { months: 1 } },
  { key: 'quotes.presetMonths', count: 3, span: { months: 3 } },
]

const relative = computed(() => relativeDate(model.value, locale.value))
</script>

<template>
  <div class="flex min-w-0 flex-col gap-1.5">
    <Label :for="id">{{ $t('quotes.validUntil') }}</Label>
    <DatePicker :id="id" v-model="model" />
    <div class="flex flex-wrap items-center gap-[6px]">
      <Button
        v-for="preset in PRESETS"
        :key="`${preset.key}-${preset.count}`"
        type="button"
        variant="outline"
        @click="model = shiftDate(new Date(), preset.span)"
      >
        {{ $t(preset.key, { count: preset.count }) }}
      </Button>
      <span v-if="relative" class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ relative }}</span>
    </div>
  </div>
</template>
