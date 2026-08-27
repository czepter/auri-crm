<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import WorkflowPanel from '@/admin/components/WorkflowPanel.vue'
import { Button } from '@/ui/button'

defineProps<{ projects: App.Crm.Data.ProjectRowData[]; workflow?: Record<string, unknown> | null }>()

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
        <button
          v-if="workflowAllowed"
          class="ml-auto flex cursor-pointer items-center gap-1.5 border-0 bg-transparent text-[13px] font-medium text-muted-foreground"
          type="button"
          @click="openWorkflow"
        >
          <svg class="size-[15px] fill-none stroke-current stroke-[1.6] [stroke-linecap:round] [stroke-linejoin:round]" viewBox="0 0 24 24">
            <rect x="5" y="4" width="14" height="17" rx="2" />
            <path d="M9 3h6v3H9z" />
            <path d="M9 11h6M9 15h4" />
          </svg>
          {{ $t('board.workflows') }}
        </button>
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

      <WorkflowPanel v-if="workflowOpen" :workflow="workflow as never" @close="workflowOpen = false" />
    </div>
  </AdminLayout>
</template>
