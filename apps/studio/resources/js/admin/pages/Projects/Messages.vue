<script setup lang="ts">
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import MessageThread from '@/admin/components/MessageThread.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import Section from '@/ui/page/Section.vue'

defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  thread: App.Engagement.Data.ThreadData | null
  mailTemplates: App.Documents.Data.MailTemplateData[]
}>()
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectShell :header="header" active="messages">
        <Section :title="$t('project.history')">
          <template v-if="thread" #actions>
            <span class="text-[12.5px] leading-[1.47] tracking-[-0.01em]">{{ thread.subject }}</span>
          </template>

          <MessageThread
            :project-id="header.id"
            :thread="thread"
            :can-manage="header.canManageMessages"
            :mail-templates="mailTemplates"
          />
        </Section>
    </ProjectShell>
  </AdminLayout>
</template>
