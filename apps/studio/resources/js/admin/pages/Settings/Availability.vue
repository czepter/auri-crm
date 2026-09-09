<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'

const props = defineProps<{
  defaultShootDurationMinutes: number
  closures: App.Settings.Data.StudioClosureData[]
}>()

const durationForm = useForm({
  default_shoot_duration_minutes: props.defaultShootDurationMinutes,
})

function saveDuration() {
  durationForm.patch('/einstellungen/verfuegbarkeit/dauer', { preserveScroll: true })
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
              <div>
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.durationLabel') }}</div>
                <form id="duration-form" @submit.prevent="saveDuration">
                  <div class="mb-6">
                    <div class="flex min-w-0 flex-col gap-1.5" style="max-width: 160px">
                      <Label for="dauer-minuten">{{ $t('settings.durationLabel') }}</Label>
                      <InputGroup>
                        <InputGroupInput
                          id="dauer-minuten"
                          v-model.number="durationForm.default_shoot_duration_minutes" class="tabular-nums"
                          type="number"
                          min="1"
                          max="1440" />
                        <InputGroupAddon align="inline-end">{{ $t('common.unitMinutes') }}</InputGroupAddon>
                      </InputGroup>
                      <span class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('settings.durationHint') }}</span>
                      <p v-if="durationForm.errors.default_shoot_duration_minutes" class="text-[11.5px] leading-[1.45] text-destructive">
                        {{ durationForm.errors.default_shoot_duration_minutes }}
                      </p>
                    </div>
                  </div>
                  <div v-if="durationForm.isDirty" class="mt-6 flex items-center gap-4 [&_[data-slot=button][data-variant=default]]:ml-0">
                    <Button type="submit" :disabled="durationForm.processing">
                      <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                      {{ $t('settings.durationSave') }}
                    </Button>
                    <span class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
                  </div>
                </form>
              </div>

              <div class="mt-8 first-of-type:mt-6">
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
