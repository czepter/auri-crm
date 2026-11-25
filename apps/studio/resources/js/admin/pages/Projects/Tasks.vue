<script setup lang="ts">
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
/*
 * Alle Aufgaben eines Auftrags, nach Fälligkeit gruppiert: was überfällig ist,
 * was diese Woche dran ist, was später kommt, und was an einem Ereignis hängt,
 * das noch nicht eingetreten ist. Letzteres hat kein Datum und bekommt keins
 * erfunden — es steht mit seinem Anker in der letzten Gruppe und wird nie
 * überfällig, weil eine Frist ohne Tag keine ist.
 */
import { computed, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { completeTask } from '@/admin/lib/undoable'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/crm/ProjectShell.vue'
import Card from '@/ui/page/Card.vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { DatePicker } from '@/ui/date-picker'
import { Label } from '@/ui/label'
import { dayWithWeekday, isoDate } from '@/admin/lib/dates'
import { parseQuickAdd } from '@/admin/lib/quickAdd'
import Chip from '@/ui/page/Chip.vue'
import { taskGroupLabel, taskGroups } from '@/admin/lib/projectOverview'

const { t } = useI18n()

const props = defineProps<{
  header: App.Crm.Screens.CaseHeaderData
  tasks: App.Crm.Data.TaskRowData[]
  shootOptions: { value: string; label: string }[]
  staffOptions: { value: string; label: string }[]
  anchors: { value: string; label: string; group: string; on: string | null }[]
}>()

const shoot = ref('all')
const audience = ref('all')
const assignee = ref('all')

const me = computed(() => (usePage().props.auth as { user: { id: string } | null }).user?.id ?? '')

const today = isoDate(new Date())

const shown = computed(() =>
  props.tasks.filter(
    (task) =>
      (shoot.value === 'all' || task.shootId === shoot.value) &&
      (audience.value === 'all' || task.audience === audience.value) &&
      (assignee.value === 'all' || task.assigneeId === (assignee.value === 'mine' ? me.value : assignee.value))
  )
)

const open = computed(() => shown.value.filter((task) => !task.isDone))
const done = computed(() => shown.value.filter((task) => task.isDone))

const groups = computed(() => taskGroups(open.value, today))

const openSummary = computed(() => {
  const late = open.value.filter((task) => task.isOverdue).length

  return [
    t('project.tasksOpen', { count: open.value.length }),
    late === 0 ? null : t('project.tasksOverdue', late),
  ]
    .filter(Boolean)
    .join(' · ')
})

const doneSummary = computed(() => done.value.map((task) => task.title).join(', '))

const form = useForm({ title: '', audience: 'internal', due_on: '', assignee: 'none' })

const quick = computed(() => parseQuickAdd(form.title))
const parsedDue = computed(() => (form.due_on === '' ? quick.value.dueOn : null))

function add() {
  form.transform((data) => ({
    ...data,
    title: parsedDue.value ? quick.value.title : data.title,
    due_on: parsedDue.value ?? data.due_on,
    assignee: data.assignee === 'none' ? null : data.assignee,
  })).post(`/auftraege/${props.header.id}/aufgaben`, {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  })
}

// Ohne Tag ist es jetzt. Mit Tag ist es eine Aussage über einen anderen —
// die Route hakt dann ab statt umzuschalten, sonst öffnete das Nachtragen
// eines Datums die Aufgabe wieder.
function complete(row: App.Crm.Data.TaskRowData, on: string | null = null) {
  if (on !== null && on === row.completedOn) return

  completeTask(row, { done: t('common.done'), undo: t('common.undo') }, { completed_on: on })
}

function assign(row: App.Crm.Data.TaskRowData, value: string) {
  router.post(`/aufgaben/${row.id}/zustaendig`, { assignee: value === 'none' ? null : value }, { preserveScroll: true })
}

function reopen(row: App.Crm.Data.TaskRowData) {
  router.post(`/aufgaben/${row.id}/wieder-oeffnen`, {}, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout :active="header.isBooked ? 'projekte' : 'anfragen'">
    <ProjectShell :header="header" active="tasks">
      <div class="flex flex-col gap-3">
        <Card :title="$t('project.tasks')" :summary="openSummary">
          <div class="mb-3 flex flex-wrap items-center gap-2.5">
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

            <Select v-if="!$page.props.soloStudio" v-model="assignee">
              <SelectTrigger class="w-44"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{{ $t('project.tasksAllAssignees') }}</SelectItem>
                <SelectItem value="mine">{{ $t('project.tasksMine') }}</SelectItem>
                <SelectItem v-for="option in staffOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <form v-if="header.canManageTasks" class="flex flex-wrap items-center gap-2.5" @submit.prevent="add">
            <Input
              v-model="form.title"
              size="sm"
              :aria-label="$t('lead.taskTitle')"
              :placeholder="$t('lead.taskTitle')"
              class="min-w-[220px] flex-1"
              type="text"
              required
            />
            <Chip v-if="parsedDue">{{ dayWithWeekday(parsedDue) }}</Chip>
            <DatePicker v-model="form.due_on" class="w-[150px]" />
            <Select v-if="!$page.props.soloStudio" v-model="form.assignee">
              <SelectTrigger class="w-44" :aria-label="$t('project.taskAssignee')"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="none">{{ $t('project.taskUnassigned') }}</SelectItem>
                <SelectItem v-for="option in staffOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <Button variant="default" type="submit" :disabled="form.processing">
              {{ $t('project.taskCreate') }}
            </Button>
          </form>

          <template v-for="group in groups" :key="group.key">
            <div class="mt-[18px] mb-1 text-small font-semibold text-ink-2">
              {{ taskGroupLabel(group.key, $t) }}
            </div>

            <div v-for="row in group.rows" :key="row.id" class="flex items-center gap-3.5 py-[9px] max-[640px]:flex-wrap max-[640px]:gap-x-2.5 max-[640px]:gap-y-1">
              <Checkbox
                :model-value="false"
                :aria-label="$t('project.taskComplete')"
                @update:model-value="complete(row)"
              />
              <span class="text-ui leading-[1.47]">{{ row.title }}</span>
              <Link v-if="row.threadId" class="text-small" :href="`/posteingang?unterhaltung=${row.threadId}`">{{ $t('tasks.thread') }}</Link>
              <span class="text-small text-ink-2">{{ row.audienceLabel }}</span>
              <span v-if="row.shootLabel" class="text-small text-faint">{{ row.shootLabel }}</span>
              <span v-if="row.isBlocked" class="text-small text-faint">{{
                $t('project.taskBlockedBy', { title: row.blockedBy })
              }}</span>
              <!-- Zugewiesen steht als Name; ohne Zuweisung steht die Auswahl,
                   die sie setzt — eine leere Spalte übernimmt niemand. -->
              <span v-if="!$page.props.soloStudio && row.assigneeName" class="text-small text-ink-2">{{ row.assigneeName }}</span>
              <Select v-else-if="!$page.props.soloStudio" :model-value="'none'" @update:model-value="(value) => assign(row, String(value))">
                <SelectTrigger class="h-6 w-36 text-small"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">{{ $t('project.taskUnassigned') }}</SelectItem>
                  <SelectItem v-for="option in staffOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <span
                class="text-small max-[640px]:ml-auto whitespace-nowrap tabular-nums"
                :class="row.isOverdue ? 'text-destructive' : 'text-ink-2'"
              >{{ row.dueOn ? dayWithWeekday(row.dueOn) : row.dueAnchorLabel }}</span>
            </div>
          </template>
        </Card>

        <!-- Erledigtes verschwand bisher ganz. Es steht hier, zusammengefaltet,
             mit dem Tag der Erledigung — das ist die Spur, nach der man sucht. -->
        <Card
          v-if="done.length"
          :title="$t('project.tasksDone', { count: done.length })"
          :summary="doneSummary"
          :default-open="false"
        >
          <div v-for="row in done" :key="row.id" class="flex items-center gap-3.5 py-[9px]">
            <span class="flex size-[15px] flex-none items-center justify-center rounded-[5px] bg-ok-bg text-ok">
              <svg
                class="size-[9px] fill-none stroke-current [stroke-linecap:round] [stroke-linejoin:round] [stroke-width:3]"
                viewBox="0 0 24 24"
              >
                <path d="M4 12l5 5L20 6" />
              </svg>
            </span>
            <span class="text-ui leading-[1.47] text-ink-2">{{ row.title }}</span>
            <span v-if="row.shootLabel" class="text-small text-faint">{{ row.shootLabel }}</span>
            <Label class="sr-only" :for="`done-${row.id}`">{{ $t('project.taskCompletedOn') }}</Label>
            <DatePicker
              :id="`done-${row.id}`"
              class="ml-auto w-[132px]"
              :model-value="row.completedOn ?? ''"
              size="sm"
              @update:model-value="(value: string) => value && complete(row, value)"
            />
            <Button variant="outline" type="button" @click="reopen(row)">
              {{ $t('project.taskReopen') }}
            </Button>
          </div>
        </Card>
      </div>
    </ProjectShell>
  </AdminLayout>
</template>
