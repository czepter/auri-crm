<script setup lang="ts">
import { computed, ref } from 'vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Toaster } from '@/ui/sonner'
import DocumentEditor from '@/admin/components/DocumentEditor.vue'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

// Full-screen compose view for every "per E-Mail senden": recipient, subject
// and message on the left, the mail on the right — the split pixieset's own
// "Share Collection" view uses. The right side is the notification's own
// server render (App\Notifications\Support\MailPreview) with its message
// paragraph swapped for what is being typed, so every line, button and
// footer the mail class adds shows exactly as it is sent. The message goes
// out as plain text (toPlainText), which is therefore what it shows too.
const MESSAGE = '%%NACHRICHT%%'

const props = withDefaults(
  defineProps<{
    recipients: { name: string; email: string }[]
    subject: string
    message: EditorDocument
    mailHtml: string
    palette?: PaletteEntry[]
    mergeFields?: { path: string; sample: string }[]
  }>(),
  { palette: () => [], mergeFields: () => [] },
)

const emit = defineEmits<{ cancel: []; send: [{ subject: string; message: EditorDocument }] }>()

const subject = ref(props.subject)
const message = ref<EditorDocument>(props.message)

function escapeHtml(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

const previewHtml = computed(() =>
  props.mailHtml.replace(new RegExp(`(<p[^>]*>)${MESSAGE}</p>`), (_, open: string) =>
    toPlainText(message.value)
      .split('\n\n')
      .map((paragraph) => `${open}${escapeHtml(paragraph)}</p>`)
      .join(''),
  ),
)

function send() {
  emit('send', { subject: subject.value, message: message.value })
}
</script>

<template>
  <div class="flex h-screen flex-col">
    <header class="flex h-16 flex-none items-center gap-3.5 px-7">
      <Button variant="ghost" class="size-8 p-0" type="button" @click="emit('cancel')">
        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6" /></svg>
      </Button>

      <h1 class="m-0 text-title leading-[1.2] font-bold tracking-[-0.02em]">{{ $t('sendMail.title') }}</h1>
    </header>

    <div class="flex min-h-0 flex-1">
      <div class="flex w-1/2 flex-none flex-col gap-5 overflow-y-auto p-6">
        <slot name="document" />

        <Field :label="$t('sendMail.recipient')" dense>
          <div class="flex flex-col gap-2">
            <div v-for="recipient in recipients" :key="recipient.email" class="flex items-center gap-2.5 rounded-md border border-border px-3 py-2.5">
              <div class="flex size-[26px] flex-none items-center justify-center rounded-full bg-sunk text-small font-semibold text-ink-2">
                {{ recipient.name.slice(0, 1) }}
              </div>
              <div class="min-w-0 flex-1">
                <div class="text-small font-semibold">{{ recipient.name }}</div>
                <div class="text-small text-ink-2">{{ recipient.email }}</div>
              </div>
            </div>
          </div>
        </Field>

        <Field v-slot="{ id }" :label="$t('sendMail.subject')" dense>
          <Input :id="id" v-model="subject" type="text" />
        </Field>

        <Field :label="$t('sendMail.message')" dense class="min-h-[160px] flex-1">
          <div class="flex-1 overflow-y-auto rounded-md border border-border px-3 py-2.5">
            <DocumentEditor v-model="message" :palette="palette" :merge-fields="mergeFields" />
          </div>
        </Field>

        <div class="flex justify-end border-t border-border pt-4">
          <Button variant="pill" type="button" @click="send">{{ $t('sendMail.submit') }}</Button>
        </div>
      </div>

      <div class="flex min-w-0 flex-1 flex-col p-6">
        <iframe :srcdoc="previewHtml" sandbox="" :title="$t('sendMail.preview')" class="min-h-0 flex-1 rounded-md border-0 bg-sunk" />
      </div>
    </div>
    <Toaster position="bottom-right" rich-colors />
  </div>
</template>
