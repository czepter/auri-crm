<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import FormSection from '@/ui/page/FormSection.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'

const props = defineProps<{ workingDays: number[] }>()

// Mo..So, ISO weekday order — the integers TaskTemplate::dueFor() rolls over.
const weekdays = [
  { value: 1, key: 'settings.dayMon' },
  { value: 2, key: 'settings.dayTue' },
  { value: 3, key: 'settings.dayWed' },
  { value: 4, key: 'settings.dayThu' },
  { value: 5, key: 'settings.dayFri' },
  { value: 6, key: 'settings.daySat' },
  { value: 7, key: 'settings.daySun' },
] as const

const workingDaysForm = useForm({ working_days: [...props.workingDays] as number[] })

function toggleWorkingDay(day: number) {
  const days = workingDaysForm.working_days
  workingDaysForm.working_days = days.includes(day) ? days.filter((d) => d !== day) : [...days, day].sort()
}

function saveWorkingDays() {
  workingDaysForm.patch('/kalender/einstellungen/arbeitstage', { preserveScroll: true })
}
</script>

<template>
  <form @submit.prevent="saveWorkingDays">
    <FormSection :title="$t('settings.workingDaysLabel')" :note="$t('settings.workingDaysHint')">
      <div class="flex flex-wrap gap-x-5 gap-y-3">
        <div v-for="day in weekdays" :key="day.value" class="flex items-center gap-2">
          <Checkbox
            :id="`wd-${day.value}`"
            :model-value="workingDaysForm.working_days.includes(day.value)"
            @update:model-value="toggleWorkingDay(day.value)"
          />
          <Label :for="`wd-${day.value}`">{{ $t(day.key) }}</Label>
        </div>
      </div>
      <p v-if="workingDaysForm.errors.working_days" class="mt-2 text-small leading-[1.45] text-destructive">{{ workingDaysForm.errors.working_days }}</p>
      <Button v-if="workingDaysForm.isDirty" class="mt-4" variant="pill" type="submit" :disabled="workingDaysForm.processing">
        {{ $t('settings.numberingSave') }}
      </Button>
    </FormSection>
  </form>
</template>
