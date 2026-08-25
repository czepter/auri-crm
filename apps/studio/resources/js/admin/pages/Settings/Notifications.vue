<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
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
    <div data-tokens>
      <PageHead :kicker="$t('settings.kicker')" :title="$t('notificationSettings.title')">
        <span v-if="form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
        <Button
          class="ml-auto"
          variant="pill"
          type="submit"
          form="notification-settings-form"
          :disabled="!form.isDirty || form.processing"
        >
          {{ $t('common.save') }}
        </Button>
      </PageHead>

      <div class="px-8 pt-7 pb-8">
        <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
          <SettingsNav active="notifications" />

          <form id="notification-settings-form" class="max-w-[460px] min-w-0 flex-1" @submit.prevent="submit">
            <!-- T-11: quiet hours only — see NotificationSettingsController's own
                 doc comment for why a per-event toggle grid does not ship here
                 yet. -->
            <div>
              <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('notificationSettings.quietHoursSection') }}</div>
              <div class="mb-6">
                <div class="flex items-center gap-2.5">
                  <Checkbox id="cb-quiet-hours-enabled" v-model="form.quiet_hours_enabled" />
                  <Label variant="inline" for="cb-quiet-hours-enabled">{{ $t('notificationSettings.quietHoursEnabledLabel') }}</Label>
                </div>
                <span class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('notificationSettings.quietHoursEnabledHint') }}</span>
              </div>
              <div class="mb-6">
                <div class="grid grid-cols-2 gap-4">
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="n-ruhe-von">{{ $t('notificationSettings.quietHoursStartLabel') }}</Label>
                    <Input
                      id="n-ruhe-von"
                      v-model="form.quiet_hours_start"
                      type="time"
                      :disabled="!form.quiet_hours_enabled" />
                    <p v-if="form.errors.quiet_hours_start" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.quiet_hours_start }}</p>
                  </div>
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="n-ruhe-bis">{{ $t('notificationSettings.quietHoursEndLabel') }}</Label>
                    <Input
                      id="n-ruhe-bis"
                      v-model="form.quiet_hours_end"
                      type="time"
                      :disabled="!form.quiet_hours_enabled" />
                    <p v-if="form.errors.quiet_hours_end" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.quiet_hours_end }}</p>
                  </div>
                </div>
                <span class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('notificationSettings.quietHoursHint') }}</span>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
