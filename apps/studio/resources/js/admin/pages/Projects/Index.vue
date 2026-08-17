<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

defineProps<{ projects: App.Crm.Data.ProjectRowData[] }>()

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
</script>

<template>
  <AdminLayout active="projekte">
    <!-- The first screen on the CRM sheet (C-01, #235). Its own theme root
         rather than the layout's, so the screens that have not been ported
         keep the stylesheet they were built on. -->
    <div data-theme="crm">
      <div class="hrow">
        <h1>{{ $t('projects.title') }}</h1>
      </div>

      <table v-if="projects.length" style="margin-top: var(--s5)">
        <thead>
          <tr>
            <th>{{ $t('projects.name') }}</th>
            <th>{{ $t('project.mainDateLabel') }}</th>
            <th>{{ $t('project.venueName') }}</th>
            <th>{{ $t('project.position') }}</th>
            <th>{{ $t('projects.contact') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="project in projects" :key="project.id">
            <td class="nm">
              <Link :href="`/auftraege/${project.id}`">{{ project.name }}</Link>
            </td>
            <td class="r">{{ project.date ? day(project.date) : '—' }}</td>
            <td class="mu">{{ project.venue ?? '—' }}</td>
            <td>{{ project.position }}</td>
            <td class="mu">{{ project.contact ?? '—' }}</td>
          </tr>
        </tbody>
      </table>

      <p v-else class="hint" style="padding: var(--s5) var(--s7)">{{ $t('projects.empty') }}</p>
    </div>
  </AdminLayout>
</template>
