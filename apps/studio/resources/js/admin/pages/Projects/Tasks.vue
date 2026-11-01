<script setup lang="ts">
import { Button } from '@/ui/button'
/*
 * Alle Aufgaben eines Auftrags, nach Fälligkeit gruppiert: was überfällig ist,
 * was diese Woche dran ist, was später kommt, und was an einem Ereignis hängt,
 * das noch nicht eingetreten ist. Letzteres hat kein Datum und bekommt keins
 * erfunden — es steht mit seinem Anker in der letzten Gruppe und wird nie
 * überfällig, weil eine Frist ohne Tag keine ist.
 */
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import TaskCreateDialog from '@/admin/components/TaskCreateDialog.vue'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import Card from '@/ui/page/Card.vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { DatePicker } from '@/ui/date-picker'
import { Label } from '@/ui/label'
import { dayWithWeekday, isoDate } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'

const { t } = useI18n()

const props = defineProps<{
  header: App.Crm.Screens.CaseHeaderData
  tasks: App.Crm.Data.TaskRowData[]
  shootOptions: { value: string; label: string }[]
  staffOptions: { value: string; label: string }[]
  anchors: { value: string; label: string; group: string; on: string | null }[]
}>()
useLiveProps(['header', 'tasks', 'shootOptions', 'staffOptions', 'anchors'])

const shoot = ref('all')
const audience = ref('all')
const assignee = ref('all')

const me = computed(() => (usePage().props.auth as { user: { id: string } | null }).user?.id ?? '')

const today = isoDate(new Date())
const weekEnd = isoDate(new Date(Date.now() + 7 * 86_400_000))

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

const groups = computed(() => [
  { key: 'project.tasksLate', rows: open.value.filter((task) => task.dueOn !== null && task.dueOn < today) },
  {
    key: 'project.tasksThisWeek',
    rows: open.value.filter((task) => task.dueOn !== null && task.dueOn >= today && task.dueOn <= weekEnd),
  },
  { key: 'project.tasksLater', rows: open.value.filter((task) => task.dueOn !== null && task.dueOn > weekEnd) },
  { key: 'project.tasksUndated', rows: open.value.filter((task) => task.dueOn === null) },
])

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

const form = useForm({ title: '', audience: 'internal', due_on: '' })

function add() {
  form.post(`/auftraege/${props.header.id}/aufgaben`, {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  })
}

// Ohne Tag ist es jetzt. Mit Tag ist es eine Aussage über einen anderen —
// die Route hakt dann ab statt umzuschalten, sonst öffnete das Nachtragen
// eines Datums die Aufgabe wieder.
function complete(row: App.Crm.Data.TaskRowData, on: string | null = null) {
  if (on !== null && on === row.completedOn) return

  router.post(`/aufgaben/${row.id}/erledigt`, { completed_on: on }, { preserveScroll: true })
}

function assign(row: App.Crm.Data.TaskRowData, value: string) {
  router.post(`/aufgaben/${row.id}/zustaendig`, { assignee: value === 'none' ? null : value }, { preserveScroll: true })
}

function reopen(row: App.Crm.Data.TaskRowData) {
  router.post(`/aufgaben/${row.id}/wieder-oeffnen`, {}, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="anfragen">
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

          <!-- Eine Box: Titel oben, Fälligkeit und Zielgruppe in der Fußzeile
               darin, der Knopf unten rechts. -->
          <form
            v-if="header.canManageTasks"
            class="flex flex-col rounded-md border border-rule focus-within:border-ring"
            @submit.prevent="add"
          >
            <input
              v-model="form.title"
              class="border-0 bg-transparent px-3.5 pt-3 pb-1.5 text-small outline-none"
              type="text"
              :placeholder="$t('lead.taskTitle')"
            >
            <div class="flex items-center gap-4 px-3 pt-1 pb-2.5">
              <DatePicker v-model="form.due_on" class="w-[150px] [&_input]:h-8 [&_input]:border-0 [&_input]:bg-transparent [&_input]:px-0" />
              <Select v-model="form.audience">
                <SelectTrigger class="h-8 w-[110px] border-0 bg-transparent px-0 text-ink-2"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="internal">{{ $t('lead.audienceInternal') }}</SelectItem>
                  <SelectItem value="client">{{ $t('lead.audienceClient') }}</SelectItem>
                </SelectContent>
              </Select>
              <!-- Anker, Shooting und Zuständigkeit passen nicht in die
                   Fußzeile und sind der seltenere Fall: sie bleiben im Dialog,
                   der sie schon kann. -->
              <TaskCreateDialog
                :project-id="header.id"
                :anchors="anchors"
                :shoot-options="shootOptions"
                :staff-options="staffOptions"
              />
              <Button class="ml-1" variant="pill" type="submit" :disabled="!form.title || form.processing">
                {{ $t('lead.addTask') }}
              </Button>
            </div>
          </form>

          <template v-for="group in groups" :key="group.key">
            <div v-if="group.rows.length" class="mt-[18px] mb-1 text-small font-semibold text-ink-2">
              {{ $t(group.key) }}
            </div>

            <div v-for="row in group.rows" :key="row.id" class="flex items-center gap-3.5 py-[9px]">
              <button
                class="size-[15px] flex-none rounded-[5px] bg-sunk-2"
                type="button"
                :aria-label="$t('project.taskComplete')"
                @click="complete(row)"
              />
              <span class="text-ui leading-[1.47]">{{ row.title }}</span>
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
                class="ml-auto text-small whitespace-nowrap tabular-nums"
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
