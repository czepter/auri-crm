<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { Field } from '@/ui/field'
import DateTimeField from '@/admin/components/crm/DateTimeField.vue'
import { Textarea } from '@/ui/textarea'
import { useI18n } from 'vue-i18n'

/*
 * One conversation and the field that continues it, on the project's
 * Nachrichten tab and on the inquiry's. "Kunde schrieb" writes in what the
 * client said somewhere else; nothing is sent for it (RecordMessage).
 */
const props = withDefaults(defineProps<{
  projectId: string
  thread: App.Engagement.Data.ThreadData | null
  canManage: boolean
  mailTemplates?: App.Documents.Data.MailTemplateData[]
  inquiry?: { author: string; at: string; body: string } | null
}>(), { mailTemplates: () => [], inquiry: null })

function longDateTime(iso: string) {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// A reply goes out on the thread the client opened (StartThread); without one
// the only line staff can add is one the client already said.
const { t } = useI18n()
const mode = ref<'reply' | 'record'>(props.thread ? 'reply' : 'record')
const modes = computed(() => [
  { value: 'reply', label: t('project.messagesReply') },
  { value: 'record', label: t('project.messagesRecord') },
])

const reply = useForm({ body: '', scheduled_for: '' })
const record = useForm({ body: '', sent_at: '' })

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
  if (mode.value === 'record') {
    record.post(`/auftraege/${props.projectId}/nachrichten/nachtrag`, {
      preserveScroll: true,
      onSuccess: () => record.reset(),
    })

    return
  }

  reply.post(`/auftraege/${props.projectId}/nachrichten`, {
    preserveScroll: true,
    onSuccess: () => reply.reset(),
  })
}

// E-13: cancelling deletes the row. It was never sent and nobody has seen
// it, so a "cancelled" tombstone would be a line the studio reads past
// forever.
function cancelScheduled(messageId: string) {
  router.delete(`/auftraege/${props.projectId}/nachrichten/${messageId}`, { preserveScroll: true })
}
</script>

<template>
  <div>
    <div v-if="inquiry" class="py-3">
      <div class="flex items-baseline gap-[9px]">
        <span class="text-small leading-[1.47] font-semibold tracking-[-0.012em]">{{ inquiry.author }}</span>
        <span class="text-small leading-[1.47] text-ink-2">{{ $t('project.lead') }}</span>
        <span class="ml-auto text-small leading-[1.47] tabular-nums text-ink-2">{{ longDateTime(inquiry.at) }}</span>
      </div>
      <div class="mt-[5px] max-w-[68ch] text-body whitespace-pre-wrap">{{ inquiry.body }}</div>
    </div>

    <div
      v-for="message in thread?.messages ?? []"
      :key="message.id"
      class="py-3"
      :class="message.fromStaff && 'pl-3.5 shadow-[inset_2px_0_0_var(--sunk-2)]'"
    >
      <div class="flex items-baseline gap-[9px]">
        <span class="text-small leading-[1.47] font-semibold tracking-[-0.012em]">{{ message.author }}</span>
        <span class="ml-auto text-small leading-[1.47] tabular-nums text-ink-2">{{ longDateTime(message.sentAt) }}</span>
      </div>
      <div class="mt-[5px] max-w-[68ch] text-body whitespace-pre-wrap">{{ message.body }}</div>
      <!-- Pending, not sent: shown where it will appear once it goes,
           with the one control that still applies to it. -->
      <div v-if="message.scheduledFor" class="mt-1.5 flex items-baseline gap-2 text-small leading-[1.47] text-ink-2">
        {{ $t('project.messagesScheduledFor', { when: longDateTime(message.scheduledFor) }) }}
        <Button type="button" variant="ghost" class="py-0 text-small text-ink-2" @click="cancelScheduled(message.id)">
          {{ $t('project.messagesCancelScheduled') }}
        </Button>
      </div>
    </div>

    <form v-if="canManage" class="mt-[18px]" @submit.prevent="send">
      <ChoiceGroup v-if="thread" v-model="mode" class="mb-2.5" :options="modes" />

      <template v-if="mode === 'reply'">
        <div v-if="usableTemplates.length" class="mb-2.5 flex flex-wrap items-center gap-1.5">
          <Button
            v-for="template in usableTemplates" :key="template.id"
            type="button" variant="secondary"
            @click="insertTemplate(template.id)"
          >
            {{ template.name }}
          </Button>
        </div>
        <p v-for="template in brokenTemplates" :key="template.id" class="m-0 mb-1.5 text-small leading-[1.47] text-destructive">
          {{ $t('project.messagesTemplateBroken', { name: template.name, fields: template.unknownFields.join(', ') }) }}
        </p>
        <p v-if="thread?.recipients.length" class="m-0 mb-1.5 text-small leading-[1.47] text-ink-2">
          {{ $t('project.messagesTo', { names: thread.recipients.join(', ') }) }}
        </p>
        <Field v-slot="{ id }" dense :label="$t('project.messagesReplyLabel')" :error="reply.errors.body">
          <Textarea
            :id="id"
            v-model="reply.body"
            rows="4" />
        </Field>
        <div class="mt-2.5 flex items-center gap-2.5">
          <DateTimeField v-model="reply.scheduled_for" :label="$t('project.messagesScheduleLabel')" />
          <Button variant="default" class="ml-auto" type="submit" :disabled="reply.processing" :state="reply.processing ? 'loading' : 'idle'">
            {{ reply.scheduled_for ? $t('project.messagesSchedule') : $t('project.messagesSend') }}
          </Button>
        </div>
        <p v-if="reply.errors.scheduled_for" class="m-0 mt-1.5 text-small leading-[1.47] text-destructive">{{ reply.errors.scheduled_for }}</p>
      </template>

      <template v-else>
        <Field v-slot="{ id }" dense :error="record.errors.body">
          <Textarea :id="id" v-model="record.body" rows="4" required />
        </Field>
        <div class="mt-2.5 flex items-center gap-2.5">
          <DateTimeField v-model="record.sent_at" :label="$t('project.messagesSentAt')" />
          <Button variant="default" class="ml-auto" type="submit" :disabled="record.processing">
            {{ $t('project.messagesRecordSave') }}
          </Button>
        </div>
        <p v-if="record.errors.sent_at" class="m-0 mt-1.5 text-small leading-[1.47] text-destructive">{{ record.errors.sent_at }}</p>
      </template>
    </form>
  </div>
</template>
