<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import SendByMailView from '@/admin/components/SendByMailView.vue'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument } from '@/admin/documents/blocks/types'

const props = defineProps<{
  draftId: string
  subject: string
  body: string
  recipients: { name: string; email: string }[]
  mailHtml: string
}>()

const message: EditorDocument = {
  blocks: props.body.split(/\n{2,}/).map((text) => ({ type: 'paragraph', data: { text } })),
}

function cancel() {
  router.visit('/posteingang')
}

function send({ subject, message }: { subject: string; message: EditorDocument }) {
  router.post(`/posteingang/entwuerfe/${props.draftId}/versenden`, { subject, message: toPlainText(message) })
}
</script>

<template>
  <SendByMailView
    :recipients="recipients"
    :subject="subject"
    :message="message"
    :mail-html="mailHtml"
    @cancel="cancel"
    @send="send"
  />
</template>
