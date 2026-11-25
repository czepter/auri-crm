<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/crm/ProjectShell.vue'
import { dayWithWeekday } from '@/admin/lib/dates'
import { groupEntries } from '@/admin/lib/groupEntries'
import { groupBy } from '@/admin/lib/projectOverview'

const props = defineProps<{
  header: App.Crm.Screens.CaseHeaderData
  thread: App.Crm.Data.ThreadEntryData[]
}>()

const page = usePage()
const byline = (author: string | null) =>
  page.props.soloStudio && author === page.props.auth.user?.name ? null : author

const groups = computed(() =>
  groupBy(
    groupEntries(props.thread.filter((entry) => entry.system).reverse()),
    (entry) => dayWithWeekday(entry.at) ?? '—',
  ),
)
</script>

<template>
  <AdminLayout active="projekte">
    <ProjectShell :header="header" active="overview">
      <template #rail><div /></template>
      <section v-for="group in groups" :key="group.key" class="mb-2">
        <h2 class="m-0 border-b border-rule pt-6 pb-2 text-[16px] font-semibold">{{ group.key }}</h2>
        <div v-for="entry in group.items" :key="entry.key" class="border-b border-rule py-2">
          <b class="block font-semibold">{{ entry.count > 1 ? $t('project.historyCount', { body: entry.body, count: entry.count }) : entry.body }}</b>
          <span v-if="byline(entry.author)" class="text-small text-ink-2">{{ byline(entry.author) }}</span>
        </div>
      </section>
    </ProjectShell>
  </AdminLayout>
</template>
