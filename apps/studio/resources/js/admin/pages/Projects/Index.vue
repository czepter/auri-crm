<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import ProjectSettings from '@/admin/components/ProjectSettings.vue'
import { Table, TableCell, TableGroupRow, TableHead, TableRow } from '@/ui/table'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { Input } from '@/ui/input'
import { isoDate } from '@/admin/lib/dates'
import { formatEurosRounded } from '@/lib/money'
import { relative, TYPE_DOT } from '@/admin/lib/boardCard'
import { useLiveProps } from '@/admin/lib/liveReload'

type Phase = App.Crm.Data.CaseRowData['phase']

const props = defineProps<{
  view: 'liste' | 'board'
  projects: App.Crm.Data.CaseRowData[]
  stages: { key: string; name: string; phase: Phase }[]
  stageSettings: Omit<InstanceType<typeof ProjectSettings>['$props'], 'workflow' | 'canManageTasks' | 'selection' | 'returnTo'> | null
  workflow?: Record<string, unknown> | null
  settingsSelection: InstanceType<typeof ProjectSettings>['$props']['selection'] | null
  returnTo: string | null
}>()
useLiveProps(['projects', 'stages', 'stageSettings'])

const workflowAllowed = computed(() => (usePage().props.auth.can ?? []).includes('manage_tasks'))

const scope = ref('running')
const stage = ref<string | null>(null)
const query = ref('')
const collapsed = ref<Phase[]>([])

function toggle(phase: Phase) {
  collapsed.value = collapsed.value.includes(phase) ? collapsed.value.filter((p) => p !== phase) : [...collapsed.value, phase]
}

const today = isoDate(new Date())

// #364: the list groups by where the work stands. Delivered reads newest
// first — the one delivered last week is the one somebody asks about.
const PHASES: [Phase, 1 | -1][] = [['upcoming', 1], ['editing', 1], ['delivered', -1], ['closed', -1]]

const inScope = computed(() => props.projects.filter((project) => scope.value === 'all' || project.phase !== 'delivered' && project.phase !== 'closed'))

const pills = computed(() => props.stages
  .map((option) => ({ ...option, count: inScope.value.filter((project) => project.positionKey === option.key).length }))
  .filter((option) => option.count > 0))

const shown = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')

  return inScope.value.filter((project) => (!stage.value || project.positionKey === stage.value)
    && (!needle || `${project.name} ${project.contact ?? ''}`.toLocaleLowerCase('de').includes(needle)))
})

const groups = computed(() => PHASES
  .map(([phase, direction]) => ({
    phase,
    rows: shown.value
      .filter((project) => project.phase === phase)
      .sort((a, b) => a.date && b.date ? direction * a.date.localeCompare(b.date) : a.date ? -1 : b.date ? 1 : 0),
  }))
  .filter((group) => group.rows.length))

const columns = computed(() => props.stages
  .filter((option) => option.phase !== 'delivered')
  .map((option) => {
    const cards = props.projects.filter((project) => project.positionKey === option.key)

    return { ...option, cards, total: cards.reduce((sum, project) => sum + (project.value ?? 0), 0) }
  }))

// Nothing travels with the pointer, so the card itself has to say it is under
// way. The insertion mark is the only thing that moves.
const moving = ref<{ id: string; column: number } | null>(null)
const target = ref<number | null>(null)
const insertAt = ref<number | null>(null)

function onDragStart(event: DragEvent, project: App.Crm.Data.CaseRowData, column: number) {
  moving.value = { id: project.id, column }
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function onDragOver(event: DragEvent, column: number, row: number | null) {
  if (moving.value === null || moving.value.column === column) return

  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  target.value = column
  insertAt.value = row
}

function onDrop(column: number) {
  if (moving.value === null || moving.value.column === column) return

  move(moving.value.id, columns.value[column].key)
  endDrag()
}

function endDrag() {
  moving.value = null
  target.value = null
  insertAt.value = null
}

function move(id: string, key: string) {
  router.post(`/auftraege/${id}/stufe`, { stage: key }, { preserveScroll: true })
}

// Without a mouse: dragging a card across five columns is unusable on an iPad.
function onKey(event: KeyboardEvent, project: App.Crm.Data.CaseRowData, column: number) {
  if (!event.metaKey && !event.ctrlKey) return

  const to = event.key === 'ArrowRight' ? column + 1 : event.key === 'ArrowLeft' ? column - 1 : null
  const key = to === null ? null : columns.value[to]?.key

  if (key) {
    event.preventDefault()
    move(project.id, key)
  }
}

const menu = ref<{ project: App.Crm.Data.CaseRowData; x: number; y: number } | null>(null)

function openMenu(event: MouseEvent, project: App.Crm.Data.CaseRowData) {
  event.preventDefault()
  menu.value = { project, x: event.clientX, y: event.clientY }
}

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

// The date passed and nobody has shot it: the one row on this screen that
// needs someone.
function late(project: App.Crm.Data.CaseRowData) {
  return project.phase === 'upcoming' && project.date !== null && project.date < today
}

function dots(project: App.Crm.Data.CaseRowData) {
  const reached = props.stages.findIndex((option) => option.key === project.positionKey)
  const upTo = reached < 0 && project.phase === 'delivered' ? props.stages.length - 1 : reached

  return props.stages.map((_, index) => index > upTo ? 'bg-mark' : project.phase === 'delivered' ? 'bg-ok' : 'bg-foreground')
}

function pick(key: string) {
  stage.value = stage.value === key ? null : key
}

function reset() {
  scope.value = 'all'
  stage.value = null
  query.value = ''
}
</script>

<template>
  <AdminLayout active="projekte">
    <!-- The first screen on the CRM sheet (C-01, #235). Its own theme root
         rather than the layout's, so the screens that have not been ported
         keep the stylesheet they were built on. -->
    <Page data-tokens wide @click="menu = null">
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('projects.title')">
          <ChoiceGroup
            v-if="projects.length"
            :model-value="view"
            class="ml-3"
            :options="[
              { value: 'liste', label: $t('projects.viewList') },
              { value: 'board', label: $t('projects.viewBoard') },
            ]"
            variant="segmented"
            @update:model-value="(ansicht) => router.get('/projekte', { ansicht }, { preserveScroll: true, preserveState: true })"
          />
          <Button v-if="stageSettings" :as="Link" class="ml-auto" variant="pill-muted" href="/auftraege/einstellungen/stufen">
            {{ $t('stages.entry') }}
          </Button>
        </PageHead>

        <template v-if="projects.length && view === 'liste'">
          <div class="flex flex-wrap items-center gap-4 px-6 pt-4.5">
            <ChoiceGroup
              v-model="scope"
              :options="[
                { value: 'running', label: $t('projects.running') },
                { value: 'all', label: $t('projects.all') },
              ]"
              variant="segmented"
              @update:model-value="stage = null"
            />
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="option in pills"
                :key="option.key"
                class="flex cursor-pointer items-center gap-1.5 rounded-[var(--r-sm)] px-3 py-1.5 text-small"
                :class="stage === option.key ? 'bg-foreground text-paper' : 'bg-sunk text-foreground'"
                type="button"
                :aria-pressed="stage === option.key"
                @click="pick(option.key)"
              >
                {{ option.name }}<span class="text-small tabular-nums opacity-60">{{ option.count }}</span>
              </button>
            </div>
            <Input v-model="query" class="ml-auto h-8 w-60" type="search" :placeholder="$t('projects.searchPlaceholder')" />
          </div>

          <Table>
            <thead>
              <tr>
                <TableHead>{{ $t('projects.name') }}</TableHead>
                <TableHead>{{ $t('project.mainDateLabel') }}</TableHead>
                <TableHead>{{ $t('project.venueName') }}</TableHead>
                <TableHead>{{ $t('project.position') }}</TableHead>
                <TableHead>{{ $t('projects.contact') }}</TableHead>
                <TableHead align="right">{{ $t('projects.openTasks') }}</TableHead>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in groups" :key="group.phase">
                <TableGroupRow :colspan="6" :open="!collapsed.includes(group.phase)" @toggle="toggle(group.phase)">
                  {{ $t(`projects.phase.${group.phase}`) }}
                  <span class="ml-1.5 font-normal">{{ group.rows.length }}</span>
                </TableGroupRow>
                <TableRow v-for="project in collapsed.includes(group.phase) ? [] : group.rows" :key="project.id">
                  <TableCell tone="name">
                    <Link :href="`/auftraege/${project.id}`">{{ project.name }}</Link>
                  </TableCell>
                  <TableCell align="right" :class="late(project) && 'text-destructive'">{{ project.date ? day(project.date) : '—' }}</TableCell>
                  <TableCell tone="muted">{{ project.venue ?? '—' }}</TableCell>
                  <TableCell>
                    <span class="flex items-center gap-2.5">
                      <span class="flex gap-[3px]">
                        <i v-for="(tone, index) in dots(project)" :key="index" class="size-1.5 rounded-full" :class="tone" />
                      </span>
                      {{ project.position }}
                    </span>
                  </TableCell>
                  <TableCell tone="muted">{{ project.contact ?? '—' }}</TableCell>
                  <TableCell align="right" tone="muted">
                    {{ project.taskCount ? $t('projects.openTasksOf', { open: project.openTasks, total: project.taskCount }) : '—' }}
                  </TableCell>
                </TableRow>
              </template>
            </tbody>
          </Table>

          <Button v-if="!groups.length" class="mx-8 mt-5" type="button" variant="pill-muted" @click="reset">
            {{ $t('projects.reset') }}
          </Button>
        </template>

        <div v-else-if="projects.length" class="flex items-stretch gap-6 overflow-x-auto px-6 pt-4 pb-6">
          <section
            v-for="(column, index) in columns"
            :key="column.key"
            class="flex min-w-0 shrink-0 grow basis-[200px] flex-col gap-2.5 rounded-[var(--r)]"
            :class="target === index && 'bg-[var(--accent-bg)]'"
            @dragover="onDragOver($event, index, null)"
            @drop.prevent="onDrop(index)"
          >
            <header class="flex items-center gap-1.5 px-1 pt-1 pb-0.5">
              <b class="text-[13.5px] leading-none font-semibold text-foreground">{{ column.name }}</b>
              <span class="ml-auto font-mono text-[12.5px] tabular-nums text-ink-2">
                {{ column.cards.length }}<template v-if="column.total"> · {{ formatEurosRounded(column.total) }}</template>
              </span>
            </header>

            <template v-for="(project, row) in column.cards" :key="project.id">
              <div v-if="target === index && insertAt === row" class="-mx-1.5 h-0.5 rounded-[1px] bg-ring" />

              <Link
                class="relative block cursor-grab rounded-[var(--r)] bg-paper p-3.5 text-inherit no-underline shadow-card transition-[background] duration-[120ms] ease-out hover:bg-accent"
                :class="moving?.id === project.id && 'cursor-grabbing'"
                :href="`/auftraege/${project.id}`"
                draggable="true"
                @dragstart="onDragStart($event, project, index)"
                @dragend="endDrag"
                @dragover="onDragOver($event, index, row)"
                @keydown="onKey($event, project, index)"
                @contextmenu="openMenu($event, project)"
              >
                <span class="block text-ui leading-[1.3] font-semibold tracking-[-0.014em]" :class="moving?.id === project.id && 'font-medium text-ink-2'">
                  {{ project.name }}
                </span>
                <span class="mt-0.5 block text-small" :class="moving?.id === project.id ? 'text-mark' : 'text-ink-2'">
                  <span :class="late(project) && moving?.id !== project.id && 'font-medium text-destructive'">{{ project.date ? day(project.date) : $t('board.openDate') }}</span>
                  <template v-if="project.venue"> · {{ project.venue }}</template>
                </span>
                <span class="mt-1.5 flex items-center gap-1.5">
                  <span class="size-1.5 flex-none rounded-full" :class="TYPE_DOT[project.typeKey] ?? 'bg-faint'" />
                  <span class="text-small leading-none text-muted-foreground">{{ project.type }}</span>
                  <span v-if="project.taskCount" class="ml-auto text-small leading-none tabular-nums text-ink-2">
                    {{ project.taskCount - project.openTasks }}/{{ project.taskCount }}
                  </span>
                </span>
                <span class="mt-[9px] flex items-center gap-2.5 border-t border-sunk-2 pt-2">
                  <span v-if="project.value !== null" class="text-small leading-none font-semibold tracking-[-0.02em] tabular-nums">
                    {{ formatEurosRounded(project.value) }}
                  </span>
                  <span
                    v-if="project.lastAt"
                    class="ml-auto inline-flex items-center bg-no-repeat bg-[length:11px_11px] pl-4 bg-[position:left_center] bg-icon-clock text-small leading-none tabular-nums text-ink-2"
                  >
                    {{ relative(project.lastAt) }}
                  </span>
                </span>
              </Link>
            </template>

            <!-- An empty stage keeps its height, or there is no target there. -->
            <div :class="target === index && 'min-h-11'" />
          </section>

          <div
            v-if="menu"
            class="fixed z-20 w-[208px] rounded-[12px] bg-paper p-[5px] shadow-[0_8px_24px_rgba(0,0,0,0.16)]"
            :style="{ left: `${menu.x}px`, top: `${menu.y}px` }"
            @click.stop
          >
            <div
              v-for="column in columns"
              :key="column.key"
              class="rounded-lg px-[9px] py-1.5 text-small"
              :class="column.key === menu.project.positionKey ? 'cursor-default text-mark' : 'cursor-pointer hover:bg-sunk'"
              @click="column.key !== menu.project.positionKey && move(menu.project.id, column.key)"
            >
              {{ column.name }}
            </div>
          </div>
        </div>

        <Empty v-else class="mx-8 mt-7" :text="$t('projects.empty')">
          <Button :as="Link" variant="pill" href="/anfragen/neu">{{ $t('leads.createKicker') }}</Button>
        </Empty>
        <ProjectSettings
          v-if="settingsSelection && stageSettings"
          v-bind="stageSettings"
          :selection="settingsSelection"
          :return-to="returnTo"
          :can-manage-tasks="workflowAllowed"
          :workflow="workflow"
          @close="router.visit(returnTo ?? '/projekte', { preserveState: true, preserveScroll: true })"
        />
      </div>
    </Page>
  </AdminLayout>
</template>
