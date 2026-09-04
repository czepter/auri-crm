<script setup lang="ts">
/*
 * Alle Aufgaben eines Auftrags, nach Fälligkeit gruppiert: was überfällig ist,
 * was diese Woche dran ist, was später kommt, und was an einem Ereignis hängt,
 * das noch nicht eingetreten ist. Letzteres hat kein Datum und bekommt keins
 * erfunden — es steht mit seinem Anker in der letzten Gruppe und wird nie
 * überfällig, weil eine Frist ohne Tag keine ist.
 */
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import TaskCreateDialog from '@/admin/components/TaskCreateDialog.vue'
import Section from '@/ui/page/Section.vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { dayWithWeekday, isoDate } from '@/admin/lib/dates'

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  tasks: App.Crm.Data.TaskRowData[]
  shootOptions: { value: string; label: string }[]
  anchors: { value: string; label: string; group: string; on: string | null }[]
}>()

const shoot = ref('all')
const audience = ref('all')
const showDone = ref(false)

const today = isoDate(new Date())
const weekEnd = isoDate(new Date(Date.now() + 7 * 86_400_000))

const shown = computed(() =>
  props.tasks.filter(
    (task) =>
      (shoot.value === 'all' || task.shootId === shoot.value) &&
      (audience.value === 'all' || task.audience === audience.value)
  )
)

const open = computed(() => shown.value.filter((task) => !task.isDone))
const done = computed(() => shown.value.filter((task) => task.isDone))

const groups = computed(() => [
  { key: 'project.tasksLate', rows: open.value.filter((task) => task.dueOn !== null && task.dueOn < today) },
  {
    key: 'project.tasksThisWeek',
    rows: open.value.filter((task) => task.dueOn !== null && task.dueOn >= today && task.dueOn <= weekEnd),
  },
  { key: 'project.tasksLater', rows: open.value.filter((task) => task.dueOn !== null && task.dueOn > weekEnd) },
  { key: 'project.tasksUndated', rows: open.value.filter((task) => task.dueOn === null) },
])

function complete(row: App.Crm.Data.TaskRowData) {
  router.post(`/aufgaben/${row.id}/erledigt`, {}, { preserveScroll: true })
}

function reopen(row: App.Crm.Data.TaskRowData) {
  router.post(`/aufgaben/${row.id}/wieder-oeffnen`, {}, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectShell :header="header" active="tasks">
      <Section :title="$t('project.tasks')">
        <template #actions>
          <Select v-model="shoot">
            <SelectTrigger class="w-44"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{{ $t('project.tasksAllShoots') }}</SelectItem>
              <SelectItem v-for="option in shootOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>

          <Select v-model="audience">
            <SelectTrigger class="w-40"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{{ $t('project.tasksAllAudiences') }}</SelectItem>
              <SelectItem value="internal">{{ $t('lead.audienceInternal') }}</SelectItem>
              <SelectItem value="client">{{ $t('lead.audienceClient') }}</SelectItem>
            </SelectContent>
          </Select>

          <TaskCreateDialog
            v-if="header.canManageTasks"
            :project-id="header.id"
            :shoot-options="shootOptions"
            :anchors="anchors"
          />
        </template>

        <p v-if="open.length === 0" class="py-2 text-[13px] text-ink-2">{{ $t('project.noTasks') }}</p>

        <template v-for="group in groups" :key="group.key">
          <div v-if="group.rows.length" class="mt-[18px] mb-1 text-[10.5px] font-semibold tracking-[0.07em] uppercase text-ink-2">
            {{ $t(group.key) }}
          </div>

          <div v-for="row in group.rows" :key="row.id" class="flex items-center gap-3.5 py-[9px]">
            <button
              class="size-[15px] flex-none rounded-[5px] bg-sunk-2"
              type="button"
              :aria-label="$t('project.taskComplete')"
              @click="complete(row)"
            />
            <span class="text-[13.5px] leading-[1.47]">{{ row.title }}</span>
            <span class="text-[10.5px] tracking-[0.05em] uppercase text-ink-2">{{ row.audienceLabel }}</span>
            <span v-if="row.shootLabel" class="text-[11.5px] text-faint">{{ row.shootLabel }}</span>
            <span v-if="row.isBlocked" class="text-[11.5px] text-faint">{{
              $t('project.taskBlockedBy', { title: row.blockedBy })
            }}</span>
            <span
              class="ml-auto text-[12.5px] whitespace-nowrap tabular-nums"
              :class="row.isOverdue ? 'text-destructive' : 'text-ink-2'"
            >{{ row.dueOn ? dayWithWeekday(row.dueOn) : row.dueAnchorLabel }}</span>
          </div>
        </template>

        <!-- Erledigtes verschwand bisher ganz. Es steht hier, zusammengefaltet,
             mit dem Tag der Erledigung — das ist die Spur, nach der man sucht. -->
        <div v-if="done.length" class="mt-6 border-t border-rule pt-3">
          <button
            class="flex w-full cursor-pointer items-center gap-2 text-[10.5px] font-semibold tracking-[0.07em] uppercase text-ink-2"
            type="button"
            @click="showDone = !showDone"
          >
            {{ $t('project.tasksDone', { count: done.length }) }}
          </button>

          <div v-if="showDone">
            <div v-for="row in done" :key="row.id" class="flex items-center gap-3.5 py-[9px]">
              <span class="flex size-[15px] flex-none items-center justify-center rounded-[5px] bg-ok-bg text-ok">
                <svg
                  class="size-[9px] fill-none stroke-current [stroke-linecap:round] [stroke-linejoin:round] [stroke-width:3]"
                  viewBox="0 0 24 24"
                >
                  <path d="M4 12l5 5L20 6" />
                </svg>
              </span>
              <span class="text-[13.5px] leading-[1.47] text-ink-2">{{ row.title }}</span>
              <span v-if="row.shootLabel" class="text-[11.5px] text-faint">{{ row.shootLabel }}</span>
              <span class="ml-auto text-[12.5px] whitespace-nowrap text-ink-2 tabular-nums">{{
                dayWithWeekday(row.completedOn)
              }}</span>
              <button
                class="cursor-pointer text-[12.5px] whitespace-nowrap text-accent"
                type="button"
                @click="reopen(row)"
              >
                {{ $t('project.taskReopen') }}
              </button>
            </div>
          </div>
        </div>
      </Section>
    </ProjectShell>
  </AdminLayout>
</template>
