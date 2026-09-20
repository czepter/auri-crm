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

type Phase = App.Crm.Data.ProjectRowData['phase']

const props = defineProps<{
  view: 'liste' | 'board'
  projects: App.Crm.Data.ProjectRowData[]
  stages: { key: string; name: string; phase: Phase }[]
  stageSettings: Omit<InstanceType<typeof ProjectSettings>['$props'], 'workflow' | 'canManageTasks' | 'selection' | 'returnTo'> | null
  workflow?: Record<string, unknown> | null
  settingsSelection: InstanceType<typeof ProjectSettings>['$props']['selection'] | null
  returnTo: string | null
}>()

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
const PHASES: [Phase, 1 | -1][] = [['upcoming', 1], ['editing', 1], ['delivered', -1]]

const inScope = computed(() => props.projects.filter((project) => scope.value === 'all' || project.phase !== 'delivered'))

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
  .map((option) => ({ ...option, cards: props.projects.filter((project) => project.positionKey === option.key) })))

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

// The date passed and nobody has shot it: the one row on this screen that
// needs someone.
function late(project: App.Crm.Data.ProjectRowData) {
  return project.phase === 'upcoming' && project.date !== null && project.date < today
}

function dots(project: App.Crm.Data.ProjectRowData) {
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
    <Page data-tokens wide>
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
          <div class="flex flex-wrap items-center gap-4 px-8 pt-4.5">
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
                class="flex cursor-pointer items-center gap-1.5 rounded-[var(--r-pill)] px-3 py-1.5 text-[12.5px]"
                :class="stage === option.key ? 'bg-foreground text-paper' : 'bg-sunk text-foreground'"
                type="button"
                :aria-pressed="stage === option.key"
                @click="pick(option.key)"
              >
                {{ option.name }}<span class="text-[11.5px] tabular-nums opacity-60">{{ option.count }}</span>
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

        <div
          v-else-if="projects.length"
          class="grid items-start gap-3 px-8 pt-5.5 pb-8"
          :style="{ gridTemplateColumns: `repeat(${columns.length}, minmax(0, 1fr))` }"
        >
          <div v-for="column in columns" :key="column.key" class="flex min-w-0 flex-col gap-2">
            <div class="flex items-baseline gap-2 px-0.5 pb-1">
              <b class="text-[12px] leading-none font-semibold tracking-[0.03em] uppercase">{{ column.name }}</b>
              <span class="ml-auto rounded-[5px] bg-sunk-2 px-[7px] py-0.5 text-[11px] font-medium text-ink-2">{{ column.cards.length }}</span>
            </div>
            <Link
              v-for="project in column.cards"
              :key="project.id"
              class="flex flex-col gap-1 rounded-lg border border-rule px-3 py-2.5 text-inherit no-underline transition-colors duration-[120ms] ease-out hover:bg-sunk"
              :href="`/auftraege/${project.id}`"
            >
              <span class="text-[13px] leading-[1.3] font-semibold tracking-[-0.01em]">{{ project.name }}</span>
              <span class="flex gap-2 text-[11.5px] leading-[1.4] text-ink-2">
                <span v-if="project.date" class="tabular-nums" :class="late(project) && 'font-medium text-destructive'">{{ day(project.date) }}</span>
                <span class="truncate">{{ project.venue ?? project.contact }}</span>
              </span>
              <span v-if="project.taskCount" class="mt-1 flex items-center gap-2">
                <span class="bar flex-1"><i :style="{ width: `${Math.round((1 - project.openTasks / project.taskCount) * 100)}%` }" /></span>
                <span class="text-[11px] text-faint tabular-nums">{{ project.taskCount - project.openTasks }}/{{ project.taskCount }}</span>
              </span>
            </Link>
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
