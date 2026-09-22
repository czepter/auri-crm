<script setup lang="ts">
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import QuoteList from '@/admin/components/quote/QuoteList.vue'

defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  quotes: App.Quotes.Data.QuoteData[]
  payments: { number: string | null; rows: { label: string; amount: number }[] } | null
}>()
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectShell :header="header" active="documents" child="quotes">
      <QuoteList
        :project-id="header.id"
        :can-manage-quotes="header.canManageQuotes"
        :quotes="quotes"
        :payments="payments"
      />
    </ProjectShell>
  </AdminLayout>
</template>
