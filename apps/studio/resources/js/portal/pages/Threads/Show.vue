<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'

type Message = {
  id: string
  author: string
  fromStaff: boolean
  body: string
  sentAt: string
}

type Thread = {
  id: string
  subject: string
  messages: Message[]
}

const props = defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  project: { id: string; name: string }
  thread: Thread | null
}>()

function longDateTime(iso: string) {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

// One form for both cases the store route accepts — starting the project's
// first thread (subject + body) or replying on the one that already exists
// (body only). subject is simply not sent once a thread exists.
const form = useForm({ subject: '', body: '' })

function send() {
  form
    .transform((data) => (props.thread ? { body: data.body } : data))
    .post(`/projekte/${props.project.id}/nachrichten`, {
      preserveScroll: true,
      onSuccess: () => form.reset('body'),
    })
}
</script>

<template>
  <PortalHeader :contact="contact" :show-switcher="showSwitcher" />

  <main class="pwrap">
    <div class="kick">{{ $t('portal.messagesKicker') }}</div>
    <h1>{{ thread ? thread.subject : $t('portal.messagesTitle') }}</h1>

    <div v-if="thread" class="msg" style="margin-top: var(--s5); gap: var(--s4)">
      <div v-for="message in thread.messages" :key="message.id" class="msgrow">
        <div class="msgtxt">
          <div class="msgwho">
            {{ message.fromStaff ? message.author : contact.name.split(' ')[0] }}
            <em>{{ longDateTime(message.sentAt) }}</em>
          </div>
          <div class="msgbody" style="-webkit-line-clamp: unset">{{ message.body }}</div>
        </div>
      </div>
    </div>
    <p v-else class="hint" style="margin-top: var(--s5)">{{ $t('portal.messagesNone') }}</p>

    <form class="composer" style="margin-top: var(--s5)" @submit.prevent="send">
      <template v-if="!thread">
        <Input
          v-model="form.subject"
          type="text"
          style="margin-bottom: var(--s3)"
          :placeholder="$t('portal.messagesSubjectPlaceholder')"
        />
        <p v-if="form.errors.subject" class="err">{{ form.errors.subject }}</p>
      </template>

      <Textarea v-model="form.body" rows="4" :placeholder="$t('portal.messagesBodyPlaceholder')" />
      <p v-if="form.errors.body" class="err">{{ form.errors.body }}</p>

      <div class="formfoot" style="margin-top: 0">
        <Button tone="warm" type="submit" :disabled="form.processing">
          {{ $t('portal.messagesSend') }}
        </Button>
      </div>
    </form>
  </main>

  <PortalFooter />
</template>
