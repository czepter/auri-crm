<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

const props = defineProps<{
  quietHoursEnabled: boolean
  quietHoursStart: string | null
  quietHoursEnd: string | null
}>()

// "HH:MM:SS" from the database (Postgres time column) down to the "HH:MM"
// <input type="time"> and StudioSettingsController's own date_format:H:i
// validation both expect.
function toInputTime(value: string | null): string {
  return value?.slice(0, 5) ?? ''
}

const form = useForm({
  quiet_hours_enabled: props.quietHoursEnabled,
  quiet_hours_start: toInputTime(props.quietHoursStart),
  quiet_hours_end: toInputTime(props.quietHoursEnd),
})

function submit() {
  form
    .transform((data) => ({
      ...data,
      quiet_hours_start: data.quiet_hours_start || null,
      quiet_hours_end: data.quiet_hours_end || null,
    }))
    .patch('/einstellungen/benachrichtigungen', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('notificationSettings.title') }}</h1>
      </div>
      <span v-if="form.isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
      <button
        class="btn"
        type="submit"
        form="notification-settings-form"
        :disabled="!form.isDirty || form.processing"
        :style="!form.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav" href="/einstellungen">{{ $t('settings.navProfile') }}</a>
        <a class="nav" href="/einstellungen/nummernkreise">{{ $t('settings.navNumbering') }}</a>
        <a class="nav" href="/einstellungen/verfuegbarkeit">{{ $t('settings.navAvailability') }}</a>
        <a class="nav" href="/einstellungen/zahlungen">{{ $t('settings.navPayments') }}</a>
        <a class="nav" href="/einstellungen/aufgabenvorlagen">{{ $t('settings.navTaskTemplates') }}</a>
        <a class="nav" href="/einstellungen/portal">{{ $t('settings.navPortal') }}</a>
        <a class="nav on" href="/einstellungen/benachrichtigungen">{{ $t('settings.navNotifications') }}</a>
      </nav>

      <form id="notification-settings-form" style="flex: 1; min-width: 0; max-width: 460px" @submit.prevent="submit">
        <!-- T-11: quiet hours only — see NotificationSettingsController's own
             doc comment for why a per-event toggle grid does not ship here
             yet. -->
        <div class="dsec" style="margin-top: 0">
          <div class="dlab">{{ $t('notificationSettings.quietHoursSection') }}</div>
          <div class="grp">
            <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
              <input v-model="form.quiet_hours_enabled" type="checkbox" />
              {{ $t('notificationSettings.quietHoursEnabledLabel') }}
            </label>
            <span class="hint">{{ $t('notificationSettings.quietHoursEnabledHint') }}</span>
          </div>
          <div class="grp">
            <div class="two">
              <div class="fld">
                <label for="n-ruhe-von">{{ $t('notificationSettings.quietHoursStartLabel') }}</label>
                <input
                  id="n-ruhe-von"
                  v-model="form.quiet_hours_start"
                  class="inp"
                  type="time"
                  :disabled="!form.quiet_hours_enabled"
                />
                <p v-if="form.errors.quiet_hours_start" class="err">{{ form.errors.quiet_hours_start }}</p>
              </div>
              <div class="fld">
                <label for="n-ruhe-bis">{{ $t('notificationSettings.quietHoursEndLabel') }}</label>
                <input
                  id="n-ruhe-bis"
                  v-model="form.quiet_hours_end"
                  class="inp"
                  type="time"
                  :disabled="!form.quiet_hours_enabled"
                />
                <p v-if="form.errors.quiet_hours_end" class="err">{{ form.errors.quiet_hours_end }}</p>
              </div>
            </div>
            <span class="hint">{{ $t('notificationSettings.quietHoursHint') }}</span>
          </div>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
