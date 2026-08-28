<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import StageSettings from '@/admin/components/StageSettings.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import WorkflowPanel from '@/admin/components/WorkflowPanel.vue'
import { Button } from '@/ui/button'

defineProps<{
  projects: App.Crm.Data.ProjectRowData[]
  stageSettings: InstanceType<typeof StageSettings>['$props'] | null
  workflow?: Record<string, unknown> | null
}>()

const stagesOpen = ref(false)

// Die Spalten kommen erst beim Oeffnen mit — sonst laedt jede Liste sie mit,
// ohne dass sie jemand sieht.
const workflowOpen = ref(false)
const workflowAllowed = computed(() => (usePage().props.auth.can ?? []).includes('manage_tasks'))

function openWorkflow() {
  workflowOpen.value = true
  router.reload({ only: ['workflow'] })
}

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

/* crm.css's own table, written out. The sheet styled td and th directly, and
   an element rule beats every utility — so the rules are gone and the values
   live here. */

/* crm.css .hrow und die Ueberschrift, die darin steht. */

/* crm.css .btn — die gefuellte Aktion. */
</script>

<template>
  <AdminLayout active="projekte">
    <!-- The first screen on the CRM sheet (C-01, #235). Its own theme root
         rather than the layout's, so the screens that have not been ported
         keep the stylesheet they were built on. -->
    <div data-tokens>
      <PageHead :title="$t('projects.title')">
        <Button v-if="stageSettings" class="ml-auto" type="button" variant="pill-muted" @click="stagesOpen = true">
          {{ $t('stages.entry') }}
        </Button>

        <Button v-if="workflowAllowed" type="button" variant="pill-muted" @click="openWorkflow">
          {{ $t('board.workflows') }}
        </Button>
      </PageHead>

      <Table v-if="projects.length">
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
          <TableRow v-for="project in projects" :key="project.id">
            <TableCell tone="name">
              <Link :href="`/auftraege/${project.id}`">{{ project.name }}</Link>
            </TableCell>
            <TableCell align="right">{{ project.date ? day(project.date) : '—' }}</TableCell>
            <TableCell tone="muted">{{ project.venue ?? '—' }}</TableCell>
            <TableCell>{{ project.position }}</TableCell>
            <TableCell tone="muted">{{ project.contact ?? '—' }}</TableCell>
            <TableCell align="right" tone="muted">
              {{ project.taskCount ? $t('projects.openTasksOf', { open: project.openTasks, total: project.taskCount }) : '—' }}
            </TableCell>
          </TableRow>
        </tbody>
      </Table>

      <Button v-else :as="Link" variant="pill" class="mt-5" href="/anfragen/neu">
        {{ $t('leads.createKicker') }}
      </Button>
      <StageSettings v-if="stagesOpen && stageSettings" v-bind="stageSettings" @close="stagesOpen = false" />

      <WorkflowPanel v-if="workflowOpen" :workflow="workflow as never" @close="workflowOpen = false" />
    </div>
  </AdminLayout>
</template>
