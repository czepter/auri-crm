<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const props = defineProps<{
  workingDays: number[]
  closures: App.Settings.Data.StudioClosureData[]
}>()

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
  workingDaysForm.patch('/einstellungen/verfuegbarkeit/arbeitstage', { preserveScroll: true })
}

// One form per closure, keyed by its id — an independent draft per row,
// matching how Settings/Numbering.vue edits its number ranges.
const closureForms = Object.fromEntries(
  props.closures.map((closure) => [
    closure.id,
    useForm({ label: closure.label, starts_on: closure.startsOn, ends_on: closure.endsOn }),
  ]),
)

function saveClosure(id: string) {
  closureForms[id].patch(`/einstellungen/verfuegbarkeit/schliesszeiten/${id}`, { preserveScroll: true })
}

function deleteClosure(id: string) {
  router.delete(`/einstellungen/verfuegbarkeit/schliesszeiten/${id}`, { preserveScroll: true })
}

const newClosureForm = useForm({ label: '', starts_on: '', ends_on: '' })

function addClosure() {
  newClosureForm.post('/einstellungen/verfuegbarkeit/schliesszeiten', {
    preserveScroll: true,
    onSuccess: () => newClosureForm.reset(),
  })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('settings.availabilityTitle')" />

        <div class="px-8 pt-7 pb-8">
          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="availability" />

            <div style="flex: 1; min-width: 0; max-width: 620px">
              <form id="working-days-form" class="mt-8 first-of-type:mt-6" @submit.prevent="saveWorkingDays">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.workingDaysLabel') }}</div>
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
                  <span class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('settings.workingDaysHint') }}</span>
                  <p v-if="workingDaysForm.errors.working_days" class="text-[11.5px] leading-[1.45] text-destructive">{{ workingDaysForm.errors.working_days }}</p>
                </div>
                <div v-if="workingDaysForm.isDirty" class="flex items-center gap-4 [&_[data-slot=button][data-variant=default]]:ml-0">
                  <Button type="submit" :disabled="workingDaysForm.processing">
                    <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                    {{ $t('settings.numberingSave') }}
                  </Button>
                  <span class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
                </div>
              </form>

              <div class="mt-8">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.closuresLabel') }}</div>


                <div v-for="closure in closures" :key="closure.id" class="mb-6">
                  <form :id="`closure-form-${closure.id}`" @submit.prevent="saveClosure(closure.id)">
                    <div class="mb-6 grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4">
                      <div class="col-span-full flex min-w-0 flex-col gap-1.5">
                        <Label :for="`label-${closure.id}`">{{ $t('settings.closureLabelField') }}</Label>
                        <Input :id="`label-${closure.id}`" v-model="closureForms[closure.id].label" type="text" maxlength="255" />
                        <p v-if="closureForms[closure.id].errors.label" class="text-[11.5px] leading-[1.45] text-destructive">{{ closureForms[closure.id].errors.label }}</p>
                      </div>
                      <div class="flex min-w-0 flex-col gap-1.5">
                        <Label :for="`starts-${closure.id}`">{{ $t('settings.closureStartsOn') }}</Label>
                        <DatePicker :id="`starts-${closure.id}`" v-model="closureForms[closure.id].starts_on" />
                        <p v-if="closureForms[closure.id].errors.starts_on" class="text-[11.5px] leading-[1.45] text-destructive">{{ closureForms[closure.id].errors.starts_on }}</p>
                      </div>
                      <div class="flex min-w-0 flex-col gap-1.5">
                        <Label :for="`ends-${closure.id}`">{{ $t('settings.closureEndsOn') }}</Label>
                        <DatePicker :id="`ends-${closure.id}`" v-model="closureForms[closure.id].ends_on" />
                        <p v-if="closureForms[closure.id].errors.ends_on" class="text-[11.5px] leading-[1.45] text-destructive">{{ closureForms[closure.id].errors.ends_on }}</p>
                      </div>
                    </div>
                    <div class="mt-6 flex items-center gap-4 [&_[data-slot=button][data-variant=default]]:ml-0">
                      <Button
                        v-if="closureForms[closure.id].isDirty"
                        type="submit"
                        :disabled="closureForms[closure.id].processing"
                      >
                        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                        {{ $t('settings.numberingSave') }}
                      </Button>
                      <span v-if="closureForms[closure.id].isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
                      <Button type="button" variant="outline" @click="deleteClosure(closure.id)">
                        {{ $t('settings.closureDelete') }}
                      </Button>
                    </div>
                  </form>
                </div>

                <form id="closure-new-form" @submit.prevent="addClosure">
                  <div class="mb-6 grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4">
                    <div class="col-span-full flex min-w-0 flex-col gap-1.5">
                      <Label for="new-label">{{ $t('settings.closureLabelField') }}</Label>
                      <Input id="new-label" v-model="newClosureForm.label" type="text" maxlength="255" />
                      <p v-if="newClosureForm.errors.label" class="text-[11.5px] leading-[1.45] text-destructive">{{ newClosureForm.errors.label }}</p>
                    </div>
                    <div class="flex min-w-0 flex-col gap-1.5">
                      <Label for="new-starts">{{ $t('settings.closureStartsOn') }}</Label>
                      <DatePicker id="new-starts" v-model="newClosureForm.starts_on" />
                      <p v-if="newClosureForm.errors.starts_on" class="text-[11.5px] leading-[1.45] text-destructive">{{ newClosureForm.errors.starts_on }}</p>
                    </div>
                    <div class="flex min-w-0 flex-col gap-1.5">
                      <Label for="new-ends">{{ $t('settings.closureEndsOn') }}</Label>
                      <DatePicker id="new-ends" v-model="newClosureForm.ends_on" />
                      <p v-if="newClosureForm.errors.ends_on" class="text-[11.5px] leading-[1.45] text-destructive">{{ newClosureForm.errors.ends_on }}</p>
                    </div>
                  </div>
                  <div class="mt-6 flex items-center gap-4 [&_[data-slot=button][data-variant=default]]:ml-0">
                    <Button type="submit" :disabled="newClosureForm.processing">
                      {{ $t('settings.closureAdd') }}
                    </Button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
