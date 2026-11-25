<script setup lang="ts">
import { computed } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import NoteThread from '@/admin/components/crm/NoteThread.vue'
import ProjectShell from '@/admin/components/crm/ProjectShell.vue'

const props = defineProps<{
  header: App.Crm.Screens.CaseHeaderData
  thread: App.Crm.Data.ThreadEntryData[]
}>()

const notes = computed(() => props.thread.filter((entry) => !entry.system).reverse())
</script>

<template>
  <AdminLayout :active="header.isBooked ? 'projekte' : 'anfragen'">
    <ProjectShell :header="header" active="overview">
      <template #rail><div /></template>
      <NoteThread :entries="notes" :action="`/auftraege/${header.id}/notizen`" />
    </ProjectShell>
  </AdminLayout>
</template>
