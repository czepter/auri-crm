<script setup lang="ts">
import { computed } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import MessageThread from '@/admin/components/MessageThread.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import Card from '@/ui/page/Card.vue'
import { dayWithWeekday } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  header: App.Crm.Screens.CaseHeaderData
  thread: App.Engagement.Data.ThreadData | null
  mailTemplates: App.Documents.Data.MailTemplateData[]
}>()
useLiveProps(['header', 'thread', 'mailTemplates'])

const summary = computed(() => {
  const last = props.thread?.messages.at(-1)

  return [props.thread?.subject, last?.author, dayWithWeekday(last?.sentAt, null)].filter(Boolean).join(' · ')
})
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectShell :header="header" active="messages">
      <Card :title="$t('project.history')" :summary="summary">
        <MessageThread
          :project-id="header.id"
          :thread="thread"
          :can-manage="header.canManageMessages"
          :mail-templates="mailTemplates"
        />
      </Card>
    </ProjectShell>
  </AdminLayout>
</template>
