<script setup lang="ts">
/*
 * Alle Aufgaben des Studios. Die Aufgabenseite eines Auftrags beantwortet
 * „was ist an diesem Auftrag offen“; hier steht, was an einem selbst offen
 * ist — die Frage, die eine Zuweisung überhaupt erst stellt.
 *
 * Ohne Zuweisung steht die Auswahl, die sie setzt: eine leere Spalte
 * übernimmt niemand.
 */
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import StudioTaskCreateDialog from '@/admin/components/StudioTaskCreateDialog.vue'
import Page from '@/ui/page/Page.vue'
import Empty from '@/ui/page/Empty.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { daysUntil } from '@/admin/lib/dates'

const props = defineProps<{
  tasks: App.Crm.Data.TaskRowData[]
  staffOptions: { value: string; label: string }[]
}>()

const solo = usePage().props.soloStudio
const TABS = solo ? (['all', 'done'] as const) : (['mine', 'all', 'done'] as const)

const tab = ref<'mine' | 'all' | 'done'>(solo ? 'all' : 'mine')
const me = computed(() => (usePage().props.auth as { user: { id: string } | null }).user?.id ?? '')

const shown = computed(() =>
  props.tasks.filter((task) =>
    tab.value === 'done' ? task.isDone : !task.isDone && (tab.value === 'all' || task.assigneeId === me.value)
  )
)

function due(task: App.Crm.Data.TaskRowData) {
  if (task.dueOn === null) return task.dueAnchorLabel ?? ''

  const days = daysUntil(task.dueOn)
  if (days === 0) return 'heute'
  if (days < 0) return `seit ${Math.abs(days)} ${Math.abs(days) === 1 ? 'Tag' : 'Tagen'}`

  return `in ${days} ${days === 1 ? 'Tag' : 'Tagen'}`
}

function initials(name: string) {
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

function firstName(name: string) {
  return name.split(/\s+/)[0]
}

function complete(task: App.Crm.Data.TaskRowData) {
  router.post(`/aufgaben/${task.id}/${task.isDone ? 'wieder-oeffnen' : 'erledigt'}`, {}, { preserveScroll: true })
}

function assign(task: App.Crm.Data.TaskRowData, value: string) {
  router.post(`/aufgaben/${task.id}/zustaendig`, { assignee: value === 'none' ? null : value }, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="aufgaben">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('tasks.kicker')" :title="$t('tasks.title')">
          <StudioTaskCreateDialog :staff-options="staffOptions" />
        </PageHead>

        <div class="px-8 pt-4 pb-8">
          <div class="mb-4 flex gap-5">
            <button
              v-for="key in TABS"
              :key="key"
              class="cursor-pointer text-ui leading-none"
              :class="tab === key ? 'font-semibold' : 'text-ink-2'"
              type="button"
              @click="tab = key"
            >
              {{ $t(`tasks.tab.${key}`) }}
            </button>
          </div>

          <Empty v-if="shown.length === 0" :text="$t('tasks.empty')" />

          <div v-else class="overflow-hidden rounded-[10px] border border-rule">
            <div
              v-for="task in shown"
              :key="task.id"
              class="flex items-center gap-4 border-b border-rule px-5 py-3.5 last:border-b-0"
            >
              <button
                class="size-[15px] flex-none rounded-[5px]"
                :class="task.isDone ? 'bg-ok-bg' : 'bg-sunk-2'"
                type="button"
                :aria-label="$t('project.taskComplete')"
                @click="complete(task)"
              />

              <span class="min-w-0 flex-1">
                <span class="block truncate text-ui">{{ task.title }}</span>
                <span class="block truncate text-small text-ink-2">
                  <Link v-if="task.contactId" class="text-inherit" :href="`/kontakte/${task.contactId}`">{{ task.contactName }}</Link>
                  <template v-else>{{ task.projectName }}</template>
                </span>
              </span>

              <span v-if="!solo && task.assigneeName" class="flex w-44 flex-none items-center gap-2">
                <span
                  class="flex size-[22px] items-center justify-center rounded-full bg-sunk-2 text-small font-semibold"
                >{{ initials(task.assigneeName) }}</span>
                <span class="truncate text-small">{{ firstName(task.assigneeName) }}</span>
              </span>
              <Select
                v-else-if="!solo"
                :model-value="'none'"
                class="w-44 flex-none"
                @update:model-value="(value) => assign(task, String(value))"
              >
                <SelectTrigger class="h-7 w-44 text-small"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">{{ $t('project.taskAssignee') }}</SelectItem>
                  <SelectItem v-for="option in staffOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>

              <span class="w-28 flex-none text-small text-ink-2">{{ due(task) }}</span>

              <span v-if="task.isOverdue" class="chip fail w-[92px] text-center">{{ $t('tasks.overdue') }}</span>
              <span
                v-else-if="!task.isDone && task.dueOn !== null && daysUntil(task.dueOn) === 0"
                class="chip now w-[92px] text-center"
              >{{ $t('tasks.today') }}</span>
              <span v-else class="w-[92px] flex-none"></span>
            </div>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
