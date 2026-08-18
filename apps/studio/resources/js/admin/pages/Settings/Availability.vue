<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const props = defineProps<{
  defaultShootDurationMinutes: number
  closures: App.Settings.Data.StudioClosureData[]
}>()

// Every other future group (Zahlungsdaten & USt, Dokumente, ...) — the same
// disabled "bald" list every settings screen carries its own copy of, per
// Settings/Index.vue and Settings/Numbering.vue. Portal is T-08's own route
// and Benachrichtigungen is T-11's, so both drop out of this list.
const comingSoon = ['navDocuments', 'navGeneral'] as const

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
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('settings.availabilityTitle') }}</h1>
      </div>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav" href="/einstellungen">{{ $t('settings.navProfile') }}</a>
        <a class="nav" href="/einstellungen/nummernkreise">{{ $t('settings.navNumbering') }}</a>
        <a class="nav on" href="/einstellungen/verfuegbarkeit">{{ $t('settings.navAvailability') }}</a>
        <a class="nav" href="/einstellungen/zahlungen">{{ $t('settings.navPayments') }}</a>
        <a class="nav" href="/einstellungen/aufgabenvorlagen">{{ $t('settings.navTaskTemplates') }}</a>
        <a class="nav" href="/einstellungen/fragebogen-vorlagen">{{ $t('settings.navFormTemplates') }}</a>
        <a class="nav" href="/einstellungen/portal">{{ $t('settings.navPortal') }}</a>
        <a class="nav" href="/einstellungen/benachrichtigungen">{{ $t('settings.navNotifications') }}</a>
        <a
          v-for="key in comingSoon"
          :key="key"
          class="nav"
          href="#"
          style="pointer-events: none; opacity: 0.5"
        >
          {{ $t(`settings.${key}`) }}
          <span class="chip next" style="margin-left: auto">{{ $t('settings.soon') }}</span>
        </a>
      </nav>

      <div style="flex: 1; min-width: 0; max-width: 620px">
        <div class="dsec" style="margin-top: 0">
          <div class="dlab">{{ $t('settings.durationLabel') }}</div>
          <form id="duration-form" @submit.prevent="saveDuration">
            <div class="grp">
              <div class="fld" style="max-width: 160px">
                <Label for="dauer-minuten">{{ $t('settings.durationLabel') }}</Label>
                <Input
                  id="dauer-minuten"
                  v-model.number="durationForm.default_shoot_duration_minutes" class="tabular-nums"
                  type="number"
                  min="1"
                  max="1440" />
                <span class="hint">{{ $t('settings.durationHint') }}</span>
                <p v-if="durationForm.errors.default_shoot_duration_minutes" class="err">
                  {{ durationForm.errors.default_shoot_duration_minutes }}
                </p>
              </div>
            </div>
            <div class="formfoot">
              <Button
                type="submit"
                :disabled="!durationForm.isDirty || durationForm.processing"
                :style="!durationForm.isDirty ? 'opacity:.4' : undefined"
              >
                <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                {{ $t('settings.durationSave') }}
              </Button>
              <span v-if="durationForm.isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
            </div>
          </form>
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('settings.closuresLabel') }}</div>

          <p v-if="closures.length === 0" class="hint">{{ $t('settings.closuresEmpty') }}</p>

          <div v-for="closure in closures" :key="closure.id" style="margin-bottom: var(--s5)">
            <form :id="`closure-form-${closure.id}`" @submit.prevent="saveClosure(closure.id)">
              <div class="fgrid grp">
                <div class="fld wide">
                  <Label :for="`label-${closure.id}`">{{ $t('settings.closureLabelField') }}</Label>
                  <Input :id="`label-${closure.id}`" v-model="closureForms[closure.id].label" type="text" maxlength="255" />
                  <p v-if="closureForms[closure.id].errors.label" class="err">{{ closureForms[closure.id].errors.label }}</p>
                </div>
                <div class="fld">
                  <Label :for="`starts-${closure.id}`">{{ $t('settings.closureStartsOn') }}</Label>
                  <DatePicker :id="`starts-${closure.id}`" v-model="closureForms[closure.id].starts_on" />
                  <p v-if="closureForms[closure.id].errors.starts_on" class="err">{{ closureForms[closure.id].errors.starts_on }}</p>
                </div>
                <div class="fld">
                  <Label :for="`ends-${closure.id}`">{{ $t('settings.closureEndsOn') }}</Label>
                  <DatePicker :id="`ends-${closure.id}`" v-model="closureForms[closure.id].ends_on" />
                  <p v-if="closureForms[closure.id].errors.ends_on" class="err">{{ closureForms[closure.id].errors.ends_on }}</p>
                </div>
              </div>
              <div class="formfoot">
                <Button
                  type="submit"
                  :disabled="!closureForms[closure.id].isDirty || closureForms[closure.id].processing"
                  :style="!closureForms[closure.id].isDirty ? 'opacity:.4' : undefined"
                >
                  <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                  {{ $t('settings.numberingSave') }}
                </Button>
                <span v-if="closureForms[closure.id].isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
                <Button type="button" variant="outline" @click="deleteClosure(closure.id)">
                  {{ $t('settings.closureDelete') }}
                </Button>
              </div>
            </form>
          </div>

          <form id="closure-new-form" @submit.prevent="addClosure">
            <div class="fgrid grp">
              <div class="fld wide">
                <Label for="new-label">{{ $t('settings.closureLabelField') }}</Label>
                <Input id="new-label" v-model="newClosureForm.label" type="text" maxlength="255" />
                <p v-if="newClosureForm.errors.label" class="err">{{ newClosureForm.errors.label }}</p>
              </div>
              <div class="fld">
                <Label for="new-starts">{{ $t('settings.closureStartsOn') }}</Label>
                <DatePicker id="new-starts" v-model="newClosureForm.starts_on" />
                <p v-if="newClosureForm.errors.starts_on" class="err">{{ newClosureForm.errors.starts_on }}</p>
              </div>
              <div class="fld">
                <Label for="new-ends">{{ $t('settings.closureEndsOn') }}</Label>
                <DatePicker id="new-ends" v-model="newClosureForm.ends_on" />
                <p v-if="newClosureForm.errors.ends_on" class="err">{{ newClosureForm.errors.ends_on }}</p>
              </div>
            </div>
            <div class="formfoot">
              <Button type="submit" :disabled="newClosureForm.processing">
                {{ $t('settings.closureAdd') }}
              </Button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
