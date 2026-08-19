<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'

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
      <Button
        type="submit"
        form="notification-settings-form"
        :disabled="!form.isDirty || form.processing"
        :style="!form.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </Button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <SettingsNav active="notifications" />

      <form id="notification-settings-form" style="flex: 1; min-width: 0; max-width: 460px" @submit.prevent="submit">
        <!-- T-11: quiet hours only — see NotificationSettingsController's own
             doc comment for why a per-event toggle grid does not ship here
             yet. -->
        <div class="dsec" style="margin-top: 0">
          <div class="dlab">{{ $t('notificationSettings.quietHoursSection') }}</div>
          <div class="grp">
            <div class="cbrow">
              <Checkbox id="cb-quiet-hours-enabled" v-model="form.quiet_hours_enabled" />
              <Label variant="inline" for="cb-quiet-hours-enabled">{{ $t('notificationSettings.quietHoursEnabledLabel') }}</Label>
            </div>
            <span class="hint">{{ $t('notificationSettings.quietHoursEnabledHint') }}</span>
          </div>
          <div class="grp">
            <div class="two">
              <div class="fld">
                <Label for="n-ruhe-von">{{ $t('notificationSettings.quietHoursStartLabel') }}</Label>
                <Input
                  id="n-ruhe-von"
                  v-model="form.quiet_hours_start"
                  type="time"
                  :disabled="!form.quiet_hours_enabled" />
                <p v-if="form.errors.quiet_hours_start" class="err">{{ form.errors.quiet_hours_start }}</p>
              </div>
              <div class="fld">
                <Label for="n-ruhe-bis">{{ $t('notificationSettings.quietHoursEndLabel') }}</Label>
                <Input
                  id="n-ruhe-bis"
                  v-model="form.quiet_hours_end"
                  type="time"
                  :disabled="!form.quiet_hours_enabled" />
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
