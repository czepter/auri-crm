<script setup lang="ts">
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import CrewStack from '@/admin/components/CrewStack.vue'
import { chipClass, dayAndMonth, weekday } from '@/admin/lib/shoots'

const { t } = useI18n()

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  shoots: App.Crm.Data.ShootData[]
  notes: App.Crm.Data.InternalNoteData[]
  tasks: App.Crm.Data.TaskRowData[]
  shootOptions: { value: string; label: string }[]
}>()

const composer = useForm({ body: '' })
const editing = ref<string | null>(null)
const draft = ref('')

function shortDate(date: string | null) {
  return date ? new Date(date).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' }) : null
}

const addingTask = ref(false)
const taskForm = useForm({ title: '', due_on: '', audience: 'internal', shoot: '' })

function createTask() {
  taskForm.post(`/auftraege/${props.header.id}/aufgaben`, {
    preserveScroll: true,
    onSuccess: () => {
      taskForm.reset()
      addingTask.value = false
    },
  })
}

function toggleTask(task: App.Crm.Data.TaskRowData) {
  router.post(`/aufgaben/${task.id}/erledigt`, {}, { preserveScroll: true })
}

function write() {
  composer.post(`/auftraege/${props.header.id}/notizen`, {
    preserveScroll: true,
    onSuccess: () => composer.reset(),
  })
}

function edit(note: App.Crm.Data.InternalNoteData) {
  editing.value = note.id
  draft.value = note.body
}

function save(id: string) {
  router.patch(`/notizen/${id}`, { body: draft.value }, {
    preserveScroll: true,
    onSuccess: () => (editing.value = null),
  })
}

function remove(id: string) {
  router.delete(`/notizen/${id}`, { preserveScroll: true })
}

// Its own confirm, worded to the one thing this button changes: this
// project's portal_visible, nothing about the contacts on it. Revoking a
// contact's access entirely lives on the contact screen instead — a
// deliberately separate control for a deliberately separate question.
function togglePortalVisibility() {
  const confirmKey = props.header.portalVisible
    ? 'project.portalVisibilityHideConfirm'
    : 'project.portalVisibilityShowConfirm'

  if (confirm(t(confirmKey))) {
    router.post(`/auftraege/${props.header.id}/portal-sichtbarkeit`, {}, { preserveScroll: true })
  }
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectHeader :header="header" active="overview" />

    <!-- The overview is self-sufficient: narrative left, facts right. Tabs
         hold depth only. -->
    <div class="split">
      <div class="stackcol">
        <div class="card static">
          <div class="cardhead">
            <span class="kick">{{ $t('project.shoots') }}</span>
            <span class="chip next">{{ $t('board.shootCount', header.shootCount) }}</span>
          </div>

          <div class="sess">
            <div
              v-for="shoot in shoots"
              :key="shoot.id"
              class="srow"
              :class="{ cancelled: shoot.isCancelled }"
            >
              <div class="stop">
                <div class="sdate">
                  {{ dayAndMonth(shoot.date) ?? '—' }}
                  <em>{{ weekday(shoot.date) ?? $t('board.openDate') }}</em>
                </div>
                <div class="sname">
                  {{ shoot.label ?? shoot.kind }}
                  <span v-if="shoot.isPrimary" class="prim">{{ $t('project.primary') }}</span>
                  <span>{{ [shoot.venue, shoot.callTime && `${shoot.callTime}–${shoot.endTime ?? ''}`].filter(Boolean).join(' · ') || '—' }}</span>
                </div>
                <CrewStack :crew="shoot.crew" />
                <span class="chip" :class="chipClass(shoot, header.position.value)">
                  {{ shoot.statusLabel }}
                </span>
              </div>
            </div>

            <p v-if="!shoots.length" class="hint">{{ $t('project.noShoots') }}</p>
          </div>

          <Link class="blkfoot" :href="`/auftraege/${header.id}/shootings`">
            {{ $t('project.toShoots') }}
          </Link>
        </div>

        <!-- Task.audience is a real field (vision §3.3), rendered as its own
             badge. It never stands in for InternalNote below, which is a
             separate model with no portal route at all. -->
        <div class="card static">
          <div class="cardhead">
            <span class="kick">{{ $t('project.tasks') }}</span>
            <button v-if="header.canManageTasks" type="button" class="abtn" @click="addingTask = !addingTask">
              {{ $t('project.taskAdd') }}
            </button>
          </div>

          <form v-if="addingTask && header.canManageTasks" class="fgrid" style="margin-bottom: var(--s4)" @submit.prevent="createTask">
            <div class="fld wide">
              <label for="task_title">{{ $t('project.taskTitle') }}</label>
              <input id="task_title" v-model="taskForm.title" class="inp" type="text" />
              <p v-if="taskForm.errors.title" class="err">{{ taskForm.errors.title }}</p>
            </div>
            <div class="fld">
              <label for="task_due">{{ $t('project.taskDue') }}<em>{{ $t('project.optional') }}</em></label>
              <input id="task_due" v-model="taskForm.due_on" class="inp" type="date" />
            </div>
            <div class="fld">
              <label for="task_audience">{{ $t('project.taskAudience') }}</label>
              <select id="task_audience" v-model="taskForm.audience" class="inp">
                <option value="internal">Intern</option>
                <option value="client">Kunde</option>
              </select>
            </div>
            <div class="fld wide" v-if="shootOptions.length">
              <label for="task_shoot">{{ $t('project.taskShoot') }}<em>{{ $t('project.optional') }}</em></label>
              <select id="task_shoot" v-model="taskForm.shoot" class="inp">
                <option value="">{{ $t('project.taskNoShoot') }}</option>
                <option v-for="option in shootOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>
            <div class="fld wide">
              <div class="formfoot" style="margin-top: 0">
                <button class="btn" type="submit" :disabled="taskForm.processing">
                  {{ $t('common.save') }}
                </button>
              </div>
            </div>
          </form>

          <div class="tlist">
            <div v-for="task in tasks" :key="task.id" class="taskrow">
              <!-- Completion is a toggle, not a delete: what was owed and when
                   it was done is the record. -->
              <button
                v-if="header.canManageTasks"
                type="button"
                class="abtn"
                :aria-label="$t(task.isDone ? 'project.taskReopen' : 'project.taskComplete')"
                @click="toggleTask(task)"
              >
                {{ task.isDone ? '✓' : '○' }}
              </button>
              <span class="ttext" :style="task.isDone ? 'color: var(--faint); text-decoration: line-through' : ''">
                {{ task.title }}
                <template v-if="task.shootLabel"> · {{ task.shootLabel }}</template>
              </span>
              <span class="tdate" :style="task.isOverdue ? 'color: var(--accent)' : ''">
                {{ task.dueOn ? shortDate(task.dueOn) : '—' }}
              </span>
              <span class="taudience" :class="task.audience">{{ task.audienceLabel }}</span>
            </div>
          </div>

          <p v-if="!tasks.length" class="hint">{{ $t('project.noTasks') }}</p>
        </div>

        <!-- Its own model, never a flag on a client-visible message. Nothing
             here has a portal route, and nothing here may acquire one. -->
        <div class="card static">
          <div class="cardhead">
            <span class="kick">{{ $t('project.notes') }}</span>
            <span class="chip next">{{ $t('project.notesInternalOnly') }}</span>
          </div>

          <div v-for="note in notes" :key="note.id" class="note">
            <div class="head">
              <b>{{ note.author }}</b>
              <span>{{ new Date(note.writtenAt).toLocaleDateString('de-DE') }}</span>
              <span v-if="header.canManageNotes" class="acts">
                <button type="button" class="abtn" @click="edit(note)">
                  {{ $t('project.noteEdit') }}
                </button>
                <button type="button" class="abtn" @click="remove(note.id)">
                  {{ $t('project.noteDelete') }}
                </button>
              </span>
            </div>

            <textarea
              v-if="editing === note.id"
              v-model="draft"
              class="inp"
              rows="3"
              @keydown.escape="editing = null"
            />
            <p v-else>{{ note.body }}</p>

            <div v-if="editing === note.id" class="formfoot">
              <button class="btn" type="button" @click="save(note.id)">{{ $t('common.save') }}</button>
            </div>
          </div>

          <form v-if="header.canManageNotes" class="composer" @submit.prevent="write">
            <textarea
              v-model="composer.body"
              class="inp"
              rows="2"
              :placeholder="$t('project.notePlaceholder')"
            />
            <p v-if="composer.errors.body" class="err">{{ composer.errors.body }}</p>
            <div class="formfoot" style="margin-top: 0">
              <button class="btn" type="submit" :disabled="composer.processing">
                {{ $t('common.save') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- ---------------- right: facts ---------------- -->
      <div class="stackcol">
        <div class="card static">
          <div class="cardhead"><span class="kick">{{ $t('project.booking') }}</span></div>
          <div class="kv">
            <div class="kvrow">
              <span>{{ $t('project.type') }}</span><span>{{ header.type }}</span>
            </div>
            <div class="kvrow">
              <span>{{ $t('project.mainDateLabel') }}</span>
              <span :class="{ tbd: !header.primaryDate }">
                {{ header.primaryDate ? new Date(header.primaryDate).toLocaleDateString('de-DE') : $t('board.openDate') }}
              </span>
            </div>
            <div class="kvrow">
              <span>{{ $t('project.position') }}</span><span><b>{{ header.position.label }}</b></span>
            </div>
            <div class="kvrow">
              <span>{{ $t('project.bookedState') }}</span>
              <span :class="{ tbd: !header.isBooked }">
                {{ header.isBooked ? $t('project.booked') : $t('project.notBooked') }}
              </span>
            </div>
            <div class="kvrow">
              <span>{{ $t('project.portalStatus') }}</span>
              <span :class="{ tbd: !header.portalVisible }">
                {{ header.portalVisible ? $t('project.portalVisible') : $t('project.portalHidden') }}
              </span>
            </div>
          </div>

          <button
            v-if="header.canManagePortalVisibility"
            type="button"
            class="abtn"
            style="margin-top: var(--s3)"
            @click="togglePortalVisibility"
          >
            {{ header.portalVisible ? $t('project.portalVisibilityHide') : $t('project.portalVisibilityShow') }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
