<script setup lang="ts">
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectShell from '@/admin/components/ProjectShell.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import Section from '@/ui/page/Section.vue'

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
    <ProjectShell :header="header" active="messages">
        <Section v-if="thread" :title="$t('project.history')">
          <template #actions>
            <span class="text-[12.5px] leading-[1.47] tracking-[-0.01em]">{{ thread.subject }}</span>
          </template>

          <div
            v-for="message in thread.messages"
            :key="message.id"
            class="py-3"
            :class="message.fromStaff && 'pl-3.5 shadow-[inset_2px_0_0_var(--sunk-2)]'"
          >
            <div class="flex items-baseline gap-[9px]">
              <span class="text-[13px] leading-[1.47] font-semibold tracking-[-0.012em]">{{ message.author }}</span>
              <span class="ml-auto text-[11.5px] leading-[1.47] tabular-nums text-ink-2">{{ longDateTime(message.sentAt) }}</span>
            </div>
            <div class="mt-[5px] max-w-[68ch] text-[13.5px] leading-[1.55] whitespace-pre-wrap">{{ message.body }}</div>
            <!-- Pending, not sent: shown where it will appear once it goes,
                 with the one control that still applies to it. -->
            <div v-if="message.scheduledFor" class="mt-1.5 flex items-baseline gap-2 text-[11.5px] leading-[1.47] text-ink-2">
              {{ $t('project.messagesScheduledFor', { when: scheduledLabel(message.scheduledFor) }) }}
              <Button type="button" variant="ghost" class="py-0 text-[11.5px] text-ink-2" @click="cancelScheduled(message.id)">
                {{ $t('project.messagesCancelScheduled') }}
              </Button>
            </div>
          </div>

          <form v-if="header.canManageMessages" class="mt-[18px]" @submit.prevent="send">
            <div v-if="usableTemplates.length" class="mb-2.5 flex flex-wrap items-center gap-1.5">
              <Button
                v-for="template in usableTemplates" :key="template.id"
                type="button" variant="pill-muted"
                @click="insertTemplate(template.id)"
              >
                {{ template.name }}
              </Button>
            </div>
            <p v-for="template in brokenTemplates" :key="template.id" class="m-0 mb-1.5 text-[11.5px] leading-[1.47] text-destructive">
              {{ $t('project.messagesTemplateBroken', { name: template.name, fields: template.unknownFields.join(', ') }) }}
            </p>
            <p v-if="thread.recipients.length" class="m-0 mb-1.5 text-[11.5px] leading-[1.47] text-ink-2">
              {{ $t('project.messagesTo', { names: thread.recipients.join(', ') }) }}
            </p>
            <Field v-slot="{ id }" dense :error="reply.errors.body">
              <Textarea
                :id="id"
                v-model="reply.body"
                rows="4"
                :placeholder="$t('project.messagesReplyPlaceholder')" />
            </Field>
            <div class="mt-2.5 flex items-center gap-2.5">
              <!-- Native datetime-local: a picker library for one optional field
                   is a dependency this screen does not need. -->
              <Input
                v-model="reply.scheduled_for"
                class="w-auto"
                type="datetime-local"
                :aria-label="$t('project.messagesScheduleLabel')"
              />
              <Button variant="pill" class="ml-auto" type="submit" :disabled="reply.processing">
                {{ reply.scheduled_for ? $t('project.messagesSchedule') : $t('project.messagesSend') }}
              </Button>
            </div>
            <p v-if="reply.errors.scheduled_for" class="m-0 mt-1.5 text-[11.5px] leading-[1.47] text-destructive">{{ reply.errors.scheduled_for }}</p>
          </form>
        </Section>

        <p v-else class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('project.messagesEmpty') }}</p>
    </ProjectShell>
  </AdminLayout>
</template>
