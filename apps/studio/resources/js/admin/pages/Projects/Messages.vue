<script setup lang="ts">
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import { Button } from '@/ui/button'
import { Textarea } from '@/ui/textarea'

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  thread: App.Engagement.Data.ThreadData | null
  mailTemplates: App.Documents.Data.MailTemplateData[]
}>()

function longDateTime(iso: string) {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const reply = useForm({ body: '', scheduled_for: '' })

// E-10: picking a template fills the composer with text already resolved
// against this project — what is shown is what gets sent. An existing draft
// is never silently thrown away.
const usableTemplates = computed(() => props.mailTemplates.filter((t) => !t.unknownFields.length))
const brokenTemplates = computed(() => props.mailTemplates.filter((t) => t.unknownFields.length))

function insertTemplate(id: string) {
  const template = usableTemplates.value.find((t) => t.id === id)
  if (!template) return

  reply.body = reply.body.trim() ? `${reply.body.trimEnd()}\n\n${template.body}` : template.body
}

function send() {
  reply.post(`/auftraege/${props.header.id}/nachrichten`, {
    preserveScroll: true,
    onSuccess: () => reply.reset(),
  })
}

// E-13: cancelling deletes the row. It was never sent and nobody has seen
// it, so a "cancelled" tombstone would be a line the studio reads past
// forever.
function cancelScheduled(messageId: string) {
  router.delete(`/auftraege/${props.header.id}/nachrichten/${messageId}`, { preserveScroll: true })
}

function scheduledLabel(iso: string) {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectHeader :header="header" active="messages" />

    <template v-if="thread">
      <div class="hrow" style="margin-bottom: var(--s4)">
        <span class="kick">{{ $t('project.messagesSubject', { subject: thread.subject }) }}</span>
      </div>

      <div class="msg" style="gap: var(--s4)">
        <div v-for="message in thread.messages" :key="message.id" class="msgrow">
          <div class="msgtxt">
            <div class="msgwho">
              {{ message.author }}
              <em>{{ longDateTime(message.sentAt) }}</em>
            </div>
            <div class="msgbody" style="-webkit-line-clamp: unset">{{ message.body }}</div>
            <!-- Pending, not sent: shown where it will appear once it goes,
                 with the one control that still applies to it. -->
            <div v-if="message.scheduledFor" class="hint">
              {{ $t('project.messagesScheduledFor', { when: scheduledLabel(message.scheduledFor) }) }}
              <Button type="button" variant="ghost" @click="cancelScheduled(message.id)">
                {{ $t('project.messagesCancelScheduled') }}
              </Button>
            </div>
          </div>
        </div>

      </div>

      <form v-if="header.canManageMessages" class="composer" @submit.prevent="send">
        <div v-if="usableTemplates.length" class="hrow" style="margin-bottom: var(--s3)">
          <Button
            v-for="template in usableTemplates" :key="template.id"
            type="button" variant="ghost"
            @click="insertTemplate(template.id)"
          >
            {{ template.name }}
          </Button>
        </div>
        <p v-for="template in brokenTemplates" :key="template.id" class="err">
          {{ $t('project.messagesTemplateBroken', { name: template.name, fields: template.unknownFields.join(', ') }) }}
        </p>
        <Textarea
          v-model="reply.body"
          rows="4"
          :placeholder="$t('project.messagesReplyPlaceholder')" />
        <p v-if="reply.errors.body" class="err">{{ reply.errors.body }}</p>
        <div class="formfoot" style="margin-top: 0">
          <!-- Native datetime-local: a picker library for one optional field
               is a dependency this screen does not need. -->
          <input
            v-model="reply.scheduled_for"
            type="datetime-local"
            :aria-label="$t('project.messagesScheduleLabel')"
          >
          <Button type="submit" :disabled="reply.processing">
            {{ reply.scheduled_for ? $t('project.messagesSchedule') : $t('project.messagesSend') }}
          </Button>
        </div>
        <p v-if="reply.errors.scheduled_for" class="err">{{ reply.errors.scheduled_for }}</p>
      </form>
    </template>

    <p v-else class="hint">{{ $t('project.messagesEmpty') }}</p>
  </AdminLayout>
</template>
