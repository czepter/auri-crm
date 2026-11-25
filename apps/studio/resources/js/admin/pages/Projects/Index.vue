<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Empty from '@/ui/page/Empty.vue'
import ProjectSettings from '@/admin/components/crm/ProjectSettings.vue'
import { Table, TableCell, TableGroupRow, TableHead, TableRow } from '@/ui/table'
import { Button } from '@/ui/button'
import { ContextMenu, ContextMenuContent, ContextMenuItem, ContextMenuTrigger } from '@/ui/context-menu'
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

const stageOptions = computed(() => pills.value.map((option) => ({ value: option.key, label: `${option.name} ${option.count}` })))

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

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
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
    <ProjectSettings
      v-if="settingsSelection && stageSettings"
      v-bind="stageSettings"
      :selection="settingsSelection"
      :return-to="returnTo"
      :can-manage-tasks="workflowAllowed"
      :workflow="workflow"
      @close="router.visit(returnTo ?? '/projekte', { preserveState: true, preserveScroll: true })"
    />
    <Page v-else data-tokens wide>
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
            @update:model-value="(ansicht) => router.get('/projekte', { ansicht }, { preserveScroll: true, preserveState: true })"
          />
          <Button v-if="stageSettings" :as="Link" class="ml-auto" variant="secondary" href="/auftraege/einstellungen/stufen">
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
              @update:model-value="stage = null"
            />
            <ChoiceGroup
              :model-value="stage ?? ''"
              :options="[{ value: '', label: `${$t('projects.all')} ${inScope.length}` }, ...stageOptions]"
              @update:model-value="(value) => (stage = value || null)"
            />
            <Input :aria-label="$t('projects.searchPlaceholder')" v-model="query" class="ml-auto h-8 w-64 shrink-0" type="search" :placeholder="$t('projects.searchPlaceholder')" />
          </div>

          <Table>
            <thead>
              <tr>
                <TableHead>{{ $t('projects.name') }}</TableHead>
                <TableHead>{{ $t('project.mainDateLabel') }}</TableHead>
                <TableHead class="max-[640px]:hidden">{{ $t('project.venueName') }}</TableHead>
                <TableHead>{{ $t('project.position') }}</TableHead>
                <TableHead class="max-[640px]:hidden">{{ $t('projects.contact') }}</TableHead>
                <TableHead align="right" class="max-[640px]:hidden">{{ $t('projects.openTasks') }}</TableHead>
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
                  <TableCell tone="muted" class="max-[640px]:hidden">{{ project.venue ?? '—' }}</TableCell>
                  <TableCell>
                    <span class="flex items-center gap-2.5">
                      <span class="flex gap-[3px] max-[640px]:hidden">
                        <i v-for="(tone, index) in dots(project)" :key="index" class="size-1.5 rounded-full" :class="tone" />
                      </span>
                      {{ project.position }}
                    </span>
                  </TableCell>
                  <TableCell tone="muted" class="max-[640px]:hidden">{{ project.contact ?? '—' }}</TableCell>
                  <TableCell align="right" tone="muted" class="max-[640px]:hidden">
                    {{ project.taskCount ? $t('projects.openTasksOf', { open: project.openTasks, total: project.taskCount }) : '—' }}
                  </TableCell>
                </TableRow>
              </template>
            </tbody>
          </Table>

          <Button v-if="!groups.length" class="mx-8 mt-5" type="button" variant="secondary" @click="reset">
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

              <ContextMenu>
              <ContextMenuTrigger as-child>
              <Link
                class="relative block cursor-grab surface-card p-3.5 text-inherit no-underline transition-[background,border-color] duration-[120ms] ease-out hover:border-line-hover hover:bg-accent"
                :class="moving?.id === project.id && 'cursor-grabbing'"
                :href="`/auftraege/${project.id}`"
                draggable="true"
                @dragstart="onDragStart($event, project, index)"
                @dragend="endDrag"
                @dragover="onDragOver($event, index, row)"
                @keydown="onKey($event, project, index)"
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
              </ContextMenuTrigger>
              <ContextMenuContent class="w-[208px]">
                <ContextMenuItem
                  v-for="option in columns"
                  :key="option.key"
                  :disabled="option.key === project.positionKey"
                  @select="move(project.id, option.key)"
                >
                  {{ option.name }}
                </ContextMenuItem>
              </ContextMenuContent>
              </ContextMenu>
            </template>

            <!-- An empty stage keeps its height, or there is no target there. -->
            <div :class="target === index && 'min-h-11'" />
          </section>
        </div>

        <Empty v-else :text="$t('projects.empty')">
          <Button :as="Link" variant="default" href="/anfragen/neu">{{ $t('leads.createKicker') }}</Button>
        </Empty>
      </div>
    </Page>
  </AdminLayout>
</template>
