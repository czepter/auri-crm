<script setup lang="ts">
/*
 * Alle Regeln in einer Karte: der Auslöser im Klartext unter dem Namen, wer
 * sie hört, die Bedingung und der letzte Lauf. Bearbeitet wird eine Regel
 * auf ihrer eigenen Seite — hier steht nur, was man vergleicht.
 */
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
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
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('automations.title')">
          <Button class="ml-auto" type="button" @click="create">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            {{ $t('automations.add') }}
          </Button>
        </PageHead>

        <div class="px-6 pt-7 pb-8">
          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="automations" />

            <div class="min-w-0 flex-1">
              <Empty v-if="!rules.length" :text="$t('automations.empty')">
                <Button variant="pill" type="button" @click="create">{{ $t('automations.add') }}</Button>
              </Empty>

              <div v-else class="overflow-hidden rounded-[var(--r)] bg-paper shadow-card">
                <Link
                  v-for="rule in rules"
                  :key="rule.id"
                  class="flex items-center gap-4 border-b border-rule px-5 py-3.5 no-underline last:border-b-0 hover:bg-sunk"
                  :href="`/einstellungen/automationen/${rule.id}`"
                >
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-ui font-medium">{{ rule.name }}</span>
                    <span class="block truncate text-small text-ink-2">{{ rule.triggerLabel }}</span>
                  </span>

                  <span class="w-24 flex-none text-small text-ink-2">{{ rule.recipientLabel }}</span>
                  <span class="w-44 flex-none text-small text-ink-2">{{ rule.conditionLabel ?? '—' }}</span>
                  <span class="w-48 flex-none text-small text-faint">{{ rule.lastRunLabel ?? '—' }}</span>

                  <span
                    class="chip w-[84px] flex-none text-center"
                    :class="rule.isActive ? 'done' : 'off'"
                  >{{ rule.isActive ? $t('automations.active') : $t('automations.paused') }}</span>
                </Link>
              </div>

              <div v-if="rules.length" class="mt-3 flex gap-8 text-small text-faint">
                <span>{{ $t('automations.nextRun', { time: '06:30' }) }}</span>
                <span>{{ $t('automations.timezone', { zone: timezone }) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
