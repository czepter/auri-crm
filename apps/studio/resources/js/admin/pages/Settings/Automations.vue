<script setup lang="ts">
/*
 * Alle Regeln nach Projektverlauf gruppiert: der Auslöser im Klartext unter dem Namen, wer
 * sie hört, die Bedingung und der letzte Lauf. Bearbeitet wird eine Regel
 * auf ihrer eigenen Seite — hier steht nur, was man vergleicht.
 */
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import Empty from '@/ui/page/Empty.vue'
import SettingsNav from '@/admin/components/settings/SettingsNav.vue'
import { Button } from '@/ui/button'
import { useLiveProps } from '@/admin/lib/liveReload'
import Chip from '@/ui/page/Chip.vue'

const props = defineProps<{ rules: App.Automation.Data.AutomationRuleData[]; timezone: string }>()
useLiveProps(['rules', 'timezone'])

const groups = computed(() => {
  const byGroup = new Map<string, App.Automation.Data.AutomationRuleData[]>()
  for (const rule of [...props.rules].sort((a, b) => a.order - b.order)) byGroup.set(rule.group, [...(byGroup.get(rule.group) ?? []), rule])

  return [...byGroup]
})

const create = () => router.post('/einstellungen/automationen')
</script>

<template>
  <AdminLayout active="einstellungen">
    <FormShell
      :kicker="$t('settings.kicker')" :title="$t('automations.title')" wide
    >
      <template #head>
        <Button type="button" @click="create">
          <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
          {{ $t('automations.add') }}
        </Button>
      </template>

      <template #nav><SettingsNav active="automations" /></template>

      <div class="min-w-0">
        <Empty v-if="!rules.length" :text="$t('automations.empty')">
          <Button variant="pill" type="button" @click="create">{{ $t('automations.add') }}</Button>
        </Empty>

        <template v-else>
          <section v-for="[group, list] in groups" :key="group" class="mb-6 last:mb-0">
            <h2 class="m-0 mb-2 text-small leading-none font-semibold text-foreground">{{ group }}</h2>
            <div class="overflow-hidden rounded-[var(--r)] bg-paper shadow-card">
              <div
                v-for="rule in list"
                :key="rule.id"
                class="relative flex items-center gap-4 border-b border-rule px-5 py-1 last:border-b-0 hover:bg-sunk"
              >
                <Link class="min-h-11 min-w-0 flex-1 content-center text-foreground no-underline after:absolute after:inset-0" :href="`/einstellungen/automationen/${rule.id}`">
                  <span class="block truncate text-ui font-medium">{{ rule.name }}</span>
                  <span class="block truncate text-small text-ink-2">{{ rule.triggerLabel }}</span>
                </Link>

                <span class="w-24 flex-none text-small text-ink-2">{{ rule.recipientLabel }}</span>
                <span class="w-44 flex-none text-small text-ink-2">{{ rule.conditionLabel }}</span>
                <span class="flex w-48 flex-none items-center gap-1.5 text-small" :class="rule.lastRunFailed ? 'text-destructive' : 'text-faint'">
                  <span v-if="rule.lastRunFailed" class="size-1.5 flex-none rounded-full bg-destructive" />
                  {{ rule.lastRunLabel }}
                </span>
                <span class="w-24 flex-none text-small">
                  <Link v-if="rule.drafts > 0" class="relative z-10 inline-flex min-h-11 items-center text-foreground underline underline-offset-2" href="/posteingang">{{ $t('automations.draftsOpen', rule.drafts) }}</Link>
                </span>

                <Chip
                  class="w-[84px] justify-center"
                  :tone="rule.lastRunFailed ? 'danger' : rule.isActive ? 'success' : 'neutral'"
                >{{ rule.lastRunFailed ? $t('automations.error') : rule.isActive ? $t('automations.active') : $t('automations.paused') }}</Chip>
              </div>
            </div>
          </section>

          <div class="mt-3 text-small text-faint">{{ $t('automations.timezone', { zone: timezone }) }}</div>
        </template>
      </div>
    </FormShell>
  </AdminLayout>
</template>
