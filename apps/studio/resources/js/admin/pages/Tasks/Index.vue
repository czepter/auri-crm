<script setup lang="ts">
/*
 * Alle Aufgaben des Studios. Die Aufgabenseite eines Auftrags beantwortet
 * „was ist an diesem Auftrag offen“; hier steht, was an einem selbst offen
 * ist — die Frage, die eine Zuweisung überhaupt erst stellt.
 *
 * Ohne Zuweisung steht die Auswahl, die sie setzt: eine leere Spalte
 * übernimmt niemand.
 */
import { computed, ref, watch } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import StudioTaskCreateDialog from '@/admin/components/StudioTaskCreateDialog.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import Page from '@/ui/page/Page.vue'
import Empty from '@/ui/page/Empty.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { daysUntil, dayWithWeekday } from '@/admin/lib/dates'

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

const selectedId = ref<string | null>(null)
const selected = computed(() => props.tasks.find((task) => task.id === selectedId.value) ?? null)

const form = useForm({ title: '', due_on: '' })

watch(selected, (task, previous) => {
  if (task?.id !== previous?.id) form.defaults({ title: task?.title ?? '', due_on: task?.dueOn ?? '' }).reset()
})

function save(task: App.Crm.Data.TaskRowData) {
  form.patch(`/aufgaben/${task.id}`, { preserveScroll: true, onSuccess: () => form.defaults() })
}

function remove(task: App.Crm.Data.TaskRowData) {
  router.delete(`/aufgaben/${task.id}`, { preserveScroll: true, onSuccess: () => (selectedId.value = null) })
}

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

        <div class="px-6 pt-4 pb-8">
          <div class="mb-4 inline-flex gap-0.5 rounded-lg bg-sunk p-[3px] text-small font-medium">
            <button
              v-for="key in TABS"
              :key="key"
              class="cursor-pointer rounded-[var(--r-sm)] border-0 bg-transparent px-2.5 py-1 leading-[1.4]"
              :class="tab === key ? 'bg-paper text-foreground shadow-[0_1px_2px_rgba(24,24,27,.08)]' : 'text-ink-2 hover:text-foreground'"
              type="button"
              @click="tab = key"
            >
              {{ $t(`tasks.tab.${key}`) }}
            </button>
          </div>

          <Empty v-if="shown.length === 0" :text="$t('tasks.empty')" />

          <div v-else class="overflow-hidden rounded-[var(--r)] bg-paper shadow-card">
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
                <button class="block max-w-full cursor-pointer truncate text-left text-ui" type="button" @click="selectedId = task.id">
                  {{ task.title }}
                </button>
                <span class="block truncate text-small text-ink-2">
                  <Link v-if="task.projectId" class="text-inherit" :href="`/auftraege/${task.projectId}`">{{ task.projectName }}</Link>
                  <Link v-else-if="task.contactId" class="text-inherit" :href="`/kontakte/${task.contactId}`">{{ task.contactName }}</Link>
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

    <Dialog :open="selected !== null" @update:open="(open) => !open && (selectedId = null)">
      <DialogContent v-if="selected">
        <DialogHeader>
          <DialogTitle>{{ selected.title }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="task-edit-form" class="mb-5 grid grid-cols-2 gap-x-6 gap-y-4" @submit.prevent="save(selected)">
            <Field v-slot="{ id }" wide :label="$t('project.taskTitle')" :error="form.errors.title">
              <Input :id="id" v-model="form.title" type="text" />
            </Field>
            <Field wide :label="$t('project.taskDue')" :error="form.errors.due_on">
              <DatePicker v-model="form.due_on" />
            </Field>
          </form>

          <dl class="grid grid-cols-[110px_minmax(0,1fr)] gap-x-6 gap-y-3 text-ui">
            <template v-if="selected.projectId">
              <dt class="text-ink-2">{{ $t('tasks.project') }}</dt>
              <dd><Link :href="`/auftraege/${selected.projectId}`">{{ selected.projectName }}</Link></dd>
            </template>
            <template v-if="selected.contactId">
              <dt class="text-ink-2">{{ $t('leads.contact') }}</dt>
              <dd><Link :href="`/kontakte/${selected.contactId}`">{{ selected.contactName }}</Link></dd>
            </template>
            <template v-if="selected.shootLabel">
              <dt class="text-ink-2">{{ $t('project.taskShoot') }}</dt>
              <dd>{{ selected.shootLabel }}</dd>
            </template>
            <template v-if="selected.dueOn === null && selected.dueAnchorLabel">
              <dt class="text-ink-2">{{ $t('tasks.anchor') }}</dt>
              <dd>{{ selected.dueAnchorLabel }}</dd>
            </template>
            <template v-if="selected.isDone && selected.completedOn">
              <dt class="text-ink-2">{{ $t('project.taskCompletedOn') }}</dt>
              <dd>{{ dayWithWeekday(selected.completedOn) }}</dd>
            </template>
            <template v-if="!solo">
              <dt class="text-ink-2">{{ $t('project.taskAssignee') }}</dt>
              <dd>
                <Select :model-value="selected.assigneeId ?? 'none'" @update:model-value="(value) => assign(selected!, String(value))">
                  <SelectTrigger class="h-8 w-full"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">{{ $t('project.taskUnassigned') }}</SelectItem>
                    <SelectItem v-for="option in staffOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </dd>
            </template>
            <template v-if="selected.isBlocked">
              <dt class="text-ink-2">{{ $t('tasks.blocked') }}</dt>
              <dd>{{ selected.blockedBy }}</dd>
            </template>
          </dl>
        </DialogBody>

        <DialogFooter>
          <Button type="submit" form="task-edit-form" :disabled="!form.isDirty || !form.title || form.processing">
            {{ $t('common.save') }}
          </Button>
          <Button variant="outline" type="button" :disabled="selected.isBlocked && !selected.isDone" @click="complete(selected)">
            {{ selected.isDone ? $t('project.taskReopen') : $t('project.taskComplete') }}
          </Button>
          <Button class="mr-auto" variant="ghost" type="button" @click="remove(selected)">{{ $t('common.delete') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>
