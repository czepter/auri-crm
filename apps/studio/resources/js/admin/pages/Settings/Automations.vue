<script setup lang="ts">
/*
 * Alle Regeln in einer Karte: der Auslöser im Klartext unter dem Namen, wer
 * sie hört, die Bedingung und der letzte Lauf. Bearbeitet wird eine Regel
 * auf ihrer eigenen Seite — hier steht nur, was man vergleicht.
 */
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'

defineProps<{ rules: App.Automation.Data.AutomationRuleData[]; timezone: string }>()

function create() {
  router.post('/einstellungen/automationen', {
    name: 'Neue Regel',
    anchor: 'per_shoot',
    offset_days: 7,
    direction: 'before',
    recipient_type: 'client',
    condition: null,
    notification_subject: 'Erinnerung',
    notification_body: 'Zu {{ project.name }}.',
    is_active: false,
  })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div data-tokens>
      <PageHead :kicker="$t('settings.kicker')" :title="$t('automations.title')">
        <Button class="ml-auto" type="button" @click="create">
          <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
          {{ $t('automations.add') }}
        </Button>
      </PageHead>

      <div class="px-8 pt-7 pb-8">
        <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
          <SettingsNav active="automations" />

          <div class="min-w-0 flex-1">
            <div class="overflow-hidden rounded-[10px] border border-rule">
              <Link
                v-for="rule in rules"
                :key="rule.id"
                class="flex items-center gap-4 border-b border-rule px-5 py-3.5 no-underline last:border-b-0 hover:bg-sunk"
                :href="`/einstellungen/automationen/${rule.id}`"
              >
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-[13.5px] font-medium">{{ rule.name }}</span>
                  <span class="block truncate text-[12px] text-ink-2">{{ rule.triggerLabel }}</span>
                </span>

                <span class="w-24 flex-none text-[12.5px] text-ink-2">{{ rule.recipientLabel }}</span>
                <span class="w-44 flex-none text-[12.5px] text-ink-2">{{ rule.conditionLabel ?? '—' }}</span>
                <span class="w-48 flex-none text-[12.5px] text-faint">{{ rule.lastRunLabel ?? '—' }}</span>

                <span
                  class="chip w-[84px] flex-none text-center"
                  :class="rule.isActive ? 'done' : 'off'"
                >{{ rule.isActive ? $t('automations.active') : $t('automations.paused') }}</span>
              </Link>
            </div>

            <div class="mt-3 flex gap-8 text-[12px] text-faint">
              <span>{{ $t('automations.nextRun', { time: '06:30' }) }}</span>
              <span>{{ $t('automations.timezone', { zone: timezone }) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
