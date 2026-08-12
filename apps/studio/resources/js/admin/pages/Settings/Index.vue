<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProfileForm from './panes/ProfileForm.vue'
import VatForm from './panes/VatForm.vue'
import DocumentsForm from './panes/DocumentsForm.vue'
import EmailForm from './panes/EmailForm.vue'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()

// Which pane of the section shell is showing — client-side only, the same
// second-level tab set the mockup draws as two static variants of one
// screen. Nothing here is routed: every form already saves through its own
// endpoint, so switching panes has no request to make.
const pane = ref<'profile' | 'vat' | 'documents' | 'email' | 'general'>('profile')

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
  if (pane.value === 'general') return generalForm
  return profileRef.value?.form
})

const formId = computed(() => {
  if (pane.value === 'vat') return 'vat-form'
  if (pane.value === 'documents') return 'documents-form'
  if (pane.value === 'email') return 'mail-form'
  if (pane.value === 'general') return 'general-form'
  return 'settings-form'
})

const paneTitle = computed(() => {
  if (pane.value === 'vat') return 'settings.vatTitle'
  if (pane.value === 'documents') return 'settings.documentsTitle'
  if (pane.value === 'email') return 'settings.mailTitle'
  if (pane.value === 'general') return 'settings.generalTitle'
  return 'settings.profileTitle'
})

// Every group named across this section's stories is live now that T-11's
// Benachrichtigungen has its own route — nothing left in this list.
const comingSoon: readonly string[] = []

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
      <button
        class="btn"
        type="submit"
        :form="formId"
        :disabled="!activeForm?.isDirty || activeForm?.processing"
        :style="!activeForm?.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav" :class="{ on: pane === 'profile' }" href="#" @click.prevent="pane = 'profile'">
          {{ $t('settings.navProfile') }}
        </a>
        <a class="nav" :class="{ on: pane === 'vat' }" href="#" @click.prevent="pane = 'vat'">
          {{ $t('settings.navVat') }}
        </a>
        <a class="nav" href="/einstellungen/nummernkreise">{{ $t('settings.navNumbering') }}</a>
        <a class="nav" :class="{ on: pane === 'documents' }" href="#" @click.prevent="pane = 'documents'">
          {{ $t('settings.navDocuments') }}
        </a>
        <a class="nav" :class="{ on: pane === 'email' }" href="#" @click.prevent="pane = 'email'">
          {{ $t('settings.navMail') }}
        </a>
        <a class="nav" href="/einstellungen/verfuegbarkeit">{{ $t('settings.navAvailability') }}</a>
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
        <a class="nav" :class="{ on: pane === 'general' }" href="#" @click.prevent="pane = 'general'">
          {{ $t('settings.navGeneral') }}
        </a>
      </nav>

      <div style="flex: 1; min-width: 0; max-width: 620px">
        <ProfileForm v-if="pane === 'profile'" ref="profileRef" :settings="settings" />
        <VatForm v-else-if="pane === 'vat'" ref="vatRef" :settings="settings" />
        <DocumentsForm v-else-if="pane === 'documents'" ref="documentsRef" :settings="settings" />
        <EmailForm v-else-if="pane === 'email'" ref="emailRef" :settings="settings" />

        <form v-else id="general-form" @submit.prevent="submitGeneral">
          <div class="dsec" style="margin-top: 0">
            <div class="dlab">{{ $t('settings.timezoneLocaleLabel') }}</div>
            <div class="grp">
              <div class="two">
                <div class="fld">
                  <label for="g-zeitzone">{{ $t('settings.timezoneLabel') }}</label>
                  <input id="g-zeitzone" v-model="generalForm.timezone" class="inp" type="text" placeholder="Europe/Berlin" />
                  <span class="hint">{{ $t('settings.timezoneHint') }}</span>
                  <p v-if="generalForm.errors.timezone" class="err">{{ generalForm.errors.timezone }}</p>
                </div>
                <div class="fld">
                  <label for="g-sprache">{{ $t('settings.localeLabel') }}</label>
                  <select id="g-sprache" v-model="generalForm.locale" class="inp">
                    <option value="de">{{ $t('settings.localeDe') }}</option>
                    <option value="de_DE">{{ $t('settings.localeDeDe') }}</option>
                    <option value="de_AT">{{ $t('settings.localeDeAt') }}</option>
                    <option value="de_CH">{{ $t('settings.localeDeCh') }}</option>
                  </select>
                  <p v-if="generalForm.errors.locale" class="err">{{ generalForm.errors.locale }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="dsec">
            <div class="dlab">{{ $t('settings.workingDaysLabel') }}</div>
            <div class="grp">
              <div style="display: flex; flex-wrap: wrap; gap: var(--s4)">
                <label
                  v-for="day in weekdays"
                  :key="day.value"
                  style="display: flex; align-items: center; gap: 8px; font: 400 13px/1.4 var(--font)"
                >
                  <input
                    type="checkbox"
                    :checked="generalForm.working_days.includes(day.value)"
                    @change="toggleWorkingDay(day.value)"
                  />
                  {{ $t(day.key) }}
                </label>
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
