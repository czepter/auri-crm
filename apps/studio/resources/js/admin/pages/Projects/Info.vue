<script setup lang="ts">
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import CaseRail from '@/admin/components/crm/CaseRail.vue'
import ProjectShell from '@/admin/components/crm/ProjectShell.vue'
import { useLiveProps } from '@/admin/lib/liveReload'

defineProps<{ header: App.Crm.Screens.CaseHeaderData }>()
useLiveProps(['header'])
</script>

<template>
  <AdminLayout active="projekte">
    <ProjectShell :header="header" active="info">
      <div class="min-[1080px]:hidden">
        <CaseRail :header="header" />
      </div>
    </ProjectShell>
  </AdminLayout>
</template>
