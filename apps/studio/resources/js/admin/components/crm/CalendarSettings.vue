<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { useEventListener } from '@vueuse/core'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'

const props = defineProps<{
  workingDays: number[]
}>()

const emit = defineEmits<{ close: [] }>()

useEventListener(window, 'keydown', (event: KeyboardEvent) => {
  if (event.key !== 'Escape') return
  if ((event.target as HTMLElement).closest?.('input, textarea, select, [role=combobox], [role=listbox], [role=dialog]')) return

  emit('close')
}, { capture: true })

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
  <div data-tokens class="fixed inset-0 z-40 overflow-y-auto bg-background">
    <div class="mx-auto w-full max-w-[720px] px-6 pb-16">
      <div class="sticky top-0 z-10 flex items-center gap-4 bg-background py-[18px]">
        <button
          :aria-label="$t('stages.done')"
          class="inline-flex cursor-pointer border-0 bg-transparent p-1 text-ink-2"
          type="button"
          @click="emit('close')"
        >
          <svg class="size-[18px] fill-none stroke-current stroke-[1.75] [stroke-linecap:round]" viewBox="0 0 24 24">
            <path d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>
        <span class="text-body leading-[1.3] font-semibold tracking-[-0.015em]">{{ $t('calendar.settingsTitle') }}</span>
        <Button class="ml-auto" type="button" @click="emit('close')">{{ $t('stages.done') }}</Button>
      </div>

      <div class="pt-6">
        <form id="working-days-form" @submit.prevent="saveWorkingDays">
          <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('settings.workingDaysLabel') }}</div>
          <div class="mb-6">
            <div class="flex flex-wrap gap-4">
              <div v-for="day in weekdays" :key="day.value" class="flex items-center gap-2">
                <Checkbox
                  :id="`wd-${day.value}`"
                  :model-value="workingDaysForm.working_days.includes(day.value)"
                  @update:model-value="toggleWorkingDay(day.value)"
                />
                <Label :for="`wd-${day.value}`">{{ $t(day.key) }}</Label>
              </div>
            </div>
            <span class="text-small leading-[1.45] font-normal text-faint">{{ $t('settings.workingDaysHint') }}</span>
            <p v-if="workingDaysForm.errors.working_days" class="text-small leading-[1.45] text-destructive">{{ workingDaysForm.errors.working_days }}</p>
          </div>
          <div v-if="workingDaysForm.isDirty" class="flex items-center gap-4 [&_[data-slot=button][data-variant=default]]:ml-0">
            <Button type="submit" :disabled="workingDaysForm.processing">
              <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
              {{ $t('settings.numberingSave') }}
            </Button>
            <span class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-small leading-[1.5] font-semibold bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
