<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import SendByMailView from '@/admin/components/SendByMailView.vue'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument } from '@/admin/documents/blocks/types'

const props = defineProps<{
  projectId: string
  contractId: string
  recipients: { name: string; email: string }[]
  mailHtml: string
}>()

const message: EditorDocument = {
  blocks: [
    { type: 'paragraph', data: { text: `Hallo ${props.recipients.map((recipient) => recipient.name).join(' und ')},` } },
    { type: 'paragraph', data: { text: 'hier euer Vertrag zur Unterschrift.' } },
  ],
}

function cancel() {
  router.visit(`/auftraege/${props.projectId}/vertrag`)
}

function send({ subject, message }: { subject: string; message: EditorDocument }) {
  router.post(`/vertraege/${props.contractId}/versenden`, { subject, message: toPlainText(message) })
}
</script>

<template>
  <SendByMailView
    :recipients="recipients"
    :subject="$t('project.contract')"
    :message="message"
    :mail-html="mailHtml"
    @cancel="cancel"
    @send="send"
  />
</template>
