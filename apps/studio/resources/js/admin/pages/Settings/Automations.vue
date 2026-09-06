<script setup lang="ts">
/*
 * Alle Regeln in einer Liste: der Auslöser im Klartext, wer sie hört, die
 * Bedingung und der letzte Lauf. Bearbeitet wird eine Regel auf ihrer
 * eigenen Seite — hier steht nur, was man vergleicht.
 */
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'
import { dayWithWeekday } from '@/admin/lib/dates'

defineProps<{ rules: App.Automation.Data.AutomationRuleData[] }>()

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
      <PageHead :kicker="$t('settings.kicker')" :title="$t('automations.title')" />

      <div class="px-8 pt-7 pb-8">
        <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
          <SettingsNav active="automations" />

          <div class="max-w-[720px] min-w-0 flex-1">
            <div class="mb-4 flex justify-end">
              <Button variant="outline" type="button" @click="create">{{ $t('automations.add') }}</Button>
            </div>

            <Link
              v-for="rule in rules"
              :key="rule.id"
              class="flex items-baseline gap-3 border-b border-rule py-3 no-underline"
              :href="`/einstellungen/automationen/${rule.id}`"
            >
              <span class="text-[13.5px] font-medium">{{ rule.name }}</span>
              <span class="text-[11.5px] text-ink-2">{{ rule.triggerLabel }}</span>
              <span class="text-[11.5px] text-faint">{{ rule.recipientLabel }}</span>
              <span v-if="rule.conditionLabel" class="text-[11.5px] text-faint">{{ rule.conditionLabel }}</span>
              <span v-if="!rule.isActive" class="chip">{{ $t('automations.inactive') }}</span>
              <span class="ml-auto text-[11.5px] whitespace-nowrap text-ink-2 tabular-nums">
                {{ rule.lastRunAt ? dayWithWeekday(rule.lastRunAt.slice(0, 10)) : '—' }}
              </span>
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
