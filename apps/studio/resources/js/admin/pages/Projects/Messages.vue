<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
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

const reply = useForm({ body: '' })

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
          </div>
        </div>

        <p v-if="!thread.messages.length" class="hint">{{ $t('project.messagesEmpty') }}</p>
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
          <Button type="submit" :disabled="reply.processing">
            {{ $t('project.messagesSend') }}
          </Button>
        </div>
      </form>
    </template>

    <p v-else class="hint">{{ $t('project.messagesEmpty') }}</p>
  </AdminLayout>
</template>
