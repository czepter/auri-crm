<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import ProfileForm from './panes/ProfileForm.vue'
import VatForm from './panes/VatForm.vue'
import DocumentsForm from './panes/DocumentsForm.vue'
import EmailForm from './panes/EmailForm.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Checkbox } from '@/ui/checkbox'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'

const props = defineProps<{

  fontPairs: { value: string; label: string; description: string }[]
  settings: App.Settings.Data.StudioSettingsData
}>()

// Which pane of the section shell is showing. In the query rather than in a
// ref, because SettingsNav reaches these four from the sibling settings
// screens too — a pane only this component could switch to was a pane those
// screens had to render as "bald".
const PANES = ['profile', 'vat', 'documents', 'email', 'capture', 'general'] as const

const page = usePage()

const pane = computed(() => {
  const asked = new URL(page.url, 'http://settings').searchParams.get('pane')

  return PANES.find((known) => known === asked) ?? 'profile'
})

// Mo..So, ISO weekday order — matches the integers StudioSettingsController
// validates working_days.* against and TaskTemplate::dueFor() rolls over.
const weekdays = [
  { value: 1, key: 'settings.dayMon' },
  { value: 2, key: 'settings.dayTue' },
  { value: 3, key: 'settings.dayWed' },
  { value: 4, key: 'settings.dayThu' },
  { value: 5, key: 'settings.dayFri' },
  { value: 6, key: 'settings.daySat' },
  { value: 7, key: 'settings.daySun' },
] as const

// working_days travels the form as the array of ISO weekday integers
// StudioSettingsController validates directly — no bitmask, no translation
// at the wire boundary, same as every other field here.
const generalForm = useForm({
  timezone: props.settings.timezone,
  locale: props.settings.locale,
  working_days: [...props.settings.workingDays] as number[],
})

// The three suggestion lists the "Sonstige" fields at capture offer, one
// entry per line — the list is short and typed rarely, so a textarea beats a
// tag editor nobody asked for.
const captureForm = useForm({
  lead_type_presets: props.settings.leadTypePresets.join('\n'),
  lead_source_presets: props.settings.leadSourcePresets.join('\n'),
  lead_channel_presets: props.settings.leadChannelPresets.join('\n'),
})

function lines(value: string) {
  return value.split('\n').map((line) => line.trim()).filter(Boolean)
}

function submitCapture() {
  captureForm
    .transform((data) => ({
      lead_type_presets: lines(data.lead_type_presets),
      lead_source_presets: lines(data.lead_source_presets),
      lead_channel_presets: lines(data.lead_channel_presets),
    }))
    .patch('/einstellungen/erfassung', { preserveScroll: true })
}

// Template refs onto the four extracted panes — each exposes { form, submit }
// (see panes/ProfileForm.vue and its siblings). Only one is ever mounted at a
// time (v-if/v-else-if below), so only one of these is non-null at once.
const profileRef = ref<InstanceType<typeof ProfileForm>>()
const vatRef = ref<InstanceType<typeof VatForm>>()
const documentsRef = ref<InstanceType<typeof DocumentsForm>>()
const emailRef = ref<InstanceType<typeof EmailForm>>()

// isDirty is Inertia's own useForm computed, comparing current values to the
// defaults the form was built with — not a bespoke computed re-implementing
// that comparison, the same thing Packages/Edit.vue and Projects/Quotes.vue
// would reach for too if either needed a dirty state.
const activeForm = computed(() => {
  if (pane.value === 'vat') return vatRef.value?.form
  if (pane.value === 'documents') return documentsRef.value?.form
  if (pane.value === 'email') return emailRef.value?.form
  if (pane.value === 'capture') return captureForm
  if (pane.value === 'general') return generalForm
  return profileRef.value?.form
})

const formId = computed(() => {
  if (pane.value === 'vat') return 'vat-form'
  if (pane.value === 'documents') return 'documents-form'
  if (pane.value === 'email') return 'mail-form'
  if (pane.value === 'capture') return 'capture-form'
  if (pane.value === 'general') return 'general-form'
  return 'settings-form'
})

const paneTitle = computed(() => {
  if (pane.value === 'vat') return 'settings.vatTitle'
  if (pane.value === 'documents') return 'settings.documentsTitle'
  if (pane.value === 'email') return 'settings.mailTitle'
  if (pane.value === 'capture') return 'settings.captureTitle'
  if (pane.value === 'general') return 'settings.generalTitle'
  return 'settings.profileTitle'
})

function toggleWorkingDay(day: number) {
  const days = generalForm.working_days
  generalForm.working_days = days.includes(day) ? days.filter((d) => d !== day) : [...days, day].sort()
}

function submitGeneral() {
  generalForm
    .transform((data) => ({
      timezone: data.timezone || null,
      locale: data.locale || null,
      working_days: data.working_days,
    }))
    .patch('/einstellungen/allgemein', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t(paneTitle) }}</h1>
      </div>
      <span v-if="activeForm?.isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
      <Button
        type="submit"
        :form="formId"
        :disabled="!activeForm?.isDirty || activeForm?.processing"
        :style="!activeForm?.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </Button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <SettingsNav :active="pane" />

      <div style="flex: 1; min-width: 0; max-width: 620px">
        <ProfileForm v-if="pane === 'profile'" ref="profileRef" :settings="settings" :font-pairs="fontPairs" />
        <VatForm v-else-if="pane === 'vat'" ref="vatRef" :settings="settings" />
        <DocumentsForm v-else-if="pane === 'documents'" ref="documentsRef" :settings="settings" />
        <EmailForm v-else-if="pane === 'email'" ref="emailRef" :settings="settings" />

        <form v-else-if="pane === 'capture'" id="capture-form" @submit.prevent="submitCapture">
          <div class="dsec" style="margin-top: 0">
            <div class="dlab">{{ $t('settings.capturePresetsLabel') }}</div>
            <div class="grp">
              <div class="fld">
                <Label for="c-typ">{{ $t('leads.type') }}</Label>
                <Textarea id="c-typ" v-model="captureForm.lead_type_presets" rows="4" />
              </div>
              <div class="fld" style="margin-top: var(--s4)">
                <Label for="c-quelle">{{ $t('leads.source') }}</Label>
                <Textarea id="c-quelle" v-model="captureForm.lead_source_presets" rows="4" />
              </div>
              <div class="fld" style="margin-top: var(--s4)">
                <Label for="c-kanal">{{ $t('leads.capturedVia') }}</Label>
                <Textarea id="c-kanal" v-model="captureForm.lead_channel_presets" rows="4" />
              </div>
              <span class="hint">{{ $t('settings.capturePresetsHint') }}</span>
            </div>
          </div>
        </form>

        <form v-else id="general-form" @submit.prevent="submitGeneral">
          <div class="dsec" style="margin-top: 0">
            <div class="dlab">{{ $t('settings.timezoneLocaleLabel') }}</div>
            <div class="grp">
              <div class="two">
                <div class="fld">
                  <Label for="g-zeitzone">{{ $t('settings.timezoneLabel') }}</Label>
                  <Input id="g-zeitzone" v-model="generalForm.timezone" type="text" placeholder="Europe/Berlin" />
                  <span class="hint">{{ $t('settings.timezoneHint') }}</span>
                  <p v-if="generalForm.errors.timezone" class="err">{{ generalForm.errors.timezone }}</p>
                </div>
                <div class="fld">
                  <Label for="g-sprache">{{ $t('settings.localeLabel') }}</Label>
                  <Select
                    :model-value="generalForm.locale"
                    @update:model-value="(value) => (generalForm.locale = String(value))"
                  >
                    <SelectTrigger id="g-sprache">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="de">{{ $t('settings.localeDe') }}</SelectItem>
                      <SelectItem value="de_DE">{{ $t('settings.localeDeDe') }}</SelectItem>
                      <SelectItem value="de_AT">{{ $t('settings.localeDeAt') }}</SelectItem>
                      <SelectItem value="de_CH">{{ $t('settings.localeDeCh') }}</SelectItem>
                    </SelectContent>
                  </Select>
                  <p v-if="generalForm.errors.locale" class="err">{{ generalForm.errors.locale }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="dsec">
            <div class="dlab">{{ $t('settings.workingDaysLabel') }}</div>
            <div class="grp">
              <div style="display: flex; flex-wrap: wrap; gap: var(--s4)">
                <div v-for="day in weekdays" :key="day.value" class="cbrow" style="gap: 8px">
                  <Checkbox
                    :id="`wd-${day.value}`"
                    :model-value="generalForm.working_days.includes(day.value)"
                    @update:model-value="toggleWorkingDay(day.value)"
                  />
                  <Label :for="`wd-${day.value}`">{{ $t(day.key) }}</Label>
                </div>
              </div>
              <span class="hint">{{ $t('settings.workingDaysHint') }}</span>
              <p v-if="generalForm.errors.working_days" class="err">{{ generalForm.errors.working_days }}</p>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
