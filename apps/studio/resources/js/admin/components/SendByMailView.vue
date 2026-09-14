<script setup lang="ts">
import { computed, ref } from 'vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import DocumentEditor from '@/admin/components/DocumentEditor.vue'
import { serialize } from '@/admin/documents/blocks/serialize'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

// Full-screen compose view for every "per E-Mail senden": recipient, subject
// and message on the left, the email as it will actually render on the right
// — the split pixieset's own "Share Collection" view uses for a gallery
// reveal. The message field is Editor.js rather than a plain textarea (an
// empty palette: no block inserter, just the paragraph tool it ships with by
// default), so its content already serializes through the same
// blocks/serialize.ts every other mail body in the app goes through.
// What differs per document lives in two slots: #card (the document inside
// the mail) and #note (what travels with it, e.g. a PDF).
const props = withDefaults(
  defineProps<{
    studioName: string
    recipientName: string
    recipientEmail: string
    subject: string
    message: EditorDocument
    cta: string
    palette?: PaletteEntry[]
    mergeFields?: { path: string; sample: string }[]
  }>(),
  { palette: () => [], mergeFields: () => [] },
)

const emit = defineEmits<{ cancel: []; send: [{ subject: string; message: EditorDocument }] }>()

const subject = ref(props.subject)
const message = ref<EditorDocument>(props.message)

const messageHtml = computed(() => serialize(message.value, 'mail'))

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

      <h1 class="m-0 text-[17px] leading-[1.2] font-bold tracking-[-0.02em]">{{ $t('sendMail.title') }}</h1>
    </header>

    <div class="flex min-h-0 flex-1">
      <div class="flex w-1/2 flex-none flex-col gap-5 overflow-y-auto p-6">
        <Field :label="$t('sendMail.recipient')" dense>
          <div class="flex items-center gap-2.5 rounded-md border border-border px-3 py-2.5">
            <div class="flex size-[26px] flex-none items-center justify-center rounded-full bg-sunk text-[11px] font-semibold text-ink-2">
              {{ recipientName.slice(0, 1) }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-[13px] font-semibold">{{ recipientName }}</div>
              <div class="text-[12.5px] text-ink-2">{{ recipientEmail }}</div>
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

        <div class="flex items-center gap-4 border-t border-border pt-4">
          <div class="flex flex-1 items-center gap-2 text-[12px] text-faint">
            <template v-if="$slots.note">
              <svg class="size-[15px] flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" /></svg>
              <slot name="note" />
            </template>
          </div>

          <Button variant="pill" type="button" @click="send">{{ $t('sendMail.submit') }}</Button>
        </div>
      </div>

      <div class="flex min-w-0 flex-1 flex-col p-6">
        <div class="flex flex-1 items-start justify-center overflow-auto rounded-md bg-sunk p-6">
          <div class="w-[520px] flex-none bg-background shadow-[0_18px_40px_-24px_rgba(20,16,14,.28)]">
            <div class="px-10 pt-8 text-center text-[19px] font-bold tracking-[-0.03em]">{{ studioName }}</div>

            <div class="px-10 pt-7 pb-9">
              <div class="prose-mail text-[14px] leading-[1.6]" v-html="messageHtml" />

              <div v-if="$slots.card" class="mt-6 overflow-hidden rounded-[10px] border border-border">
                <slot name="card" />
              </div>

              <a href="#" class="mt-6 inline-block rounded-full bg-action px-[30px] py-3.5 text-[13px] font-semibold text-action-foreground">{{ cta }}</a>
            </div>

            <div class="border-t border-border px-10 pt-5.5 pb-7.5 text-center">
              <div class="text-[12px] font-semibold text-ink-2">{{ studioName }}</div>
              <div class="mt-1.5 text-[11.5px] text-faint">{{ $t('sendMail.questions') }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.prose-mail :deep(p) {
  margin: 0 0 0.7em;
}
.prose-mail :deep(p:last-child) {
  margin-bottom: 0;
}
</style>
