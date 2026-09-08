<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import ProjectSettings from '@/admin/components/ProjectSettings.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import { Button } from '@/ui/button'

defineProps<{
  projects: App.Crm.Data.ProjectRowData[]
  stageSettings: Omit<InstanceType<typeof ProjectSettings>['$props'], 'workflow' | 'canManageTasks'> | null
  workflow?: Record<string, unknown> | null
}>()

const settingsOpen = ref(false)
const workflowAllowed = computed(() => (usePage().props.auth.can ?? []).includes('manage_tasks'))

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
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('projects.title')">
          <Button v-if="stageSettings" class="ml-auto" type="button" variant="pill-muted" @click="settingsOpen = true">
            {{ $t('stages.entry') }}
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
        <ProjectSettings
          v-if="settingsOpen && stageSettings"
          v-bind="stageSettings"
          :can-manage-tasks="workflowAllowed"
          :workflow="workflow"
          @close="settingsOpen = false"
        />
      </div>
    </Page>
  </AdminLayout>
</template>
